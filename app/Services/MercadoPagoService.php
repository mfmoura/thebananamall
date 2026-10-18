<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Jobs\ProcessarPedidoPagamento;
use App\Models\Pedido;
use App\Models\PedidoPagamento;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MercadoPagoService
{
    public function __construct(private readonly PedidoService $pedidos) {}

    public function configuracaoPublica(): array
    {
        $this->credencial();
        $publicKey = trim((string) config('mercadopago.public_key'));
        abort_if($publicKey === '', 503, 'Pagamento temporariamente indisponível.');

        return ['public_key' => $publicKey, 'parcelas' => 1];
    }

    public function criar(Pedido $pedido, array $dados): PedidoPagamento
    {
        $this->credencial();
        // Persiste a chave ANTES da chamada: um timeout deve reutilizar a mesma cobrança.
        $pagamento = DB::transaction(function () use ($pedido, $dados): PedidoPagamento {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $codigo = $pedido->formaPagamento->codigo;
            $esperado = match ($dados['meio']) {
                'pix' => 'pix', 'credit_card' => 'cartao_credito', 'debit_card' => 'cartao_debito',
            };
            abort_unless($codigo === $esperado, 422, 'Meio diferente da forma de pagamento do pedido.');
            abort_if((int) ($dados['parcelas'] ?? 1) !== 1, 422, 'Este checkout permite pagamento em uma parcela.');
            $hash = hash_hmac('sha256', json_encode($dados, JSON_THROW_ON_ERROR), config('app.key'));
            $existente = PedidoPagamento::withTrashed()->where('pedido_id', $pedido->id)->first();
            if ($existente) {
                abort_unless(hash_equals($existente->requisicao_hash, $hash), 409, 'Já existe uma tentativa de pagamento para este pedido.');

                return $existente;
            }

            abort_unless($pedido->status === StatusPedido::AguardandoPagamento, 409, 'Pedido indisponível para pagamento.');

            $method = $dados['meio'] === 'pix'
                ? ['id' => 'pix', 'type' => 'bank_transfer']
                : ['id' => $dados['payment_method_id'], 'type' => $dados['meio'], 'token' => $dados['token'], 'installments' => (int) $dados['parcelas']];

            return PedidoPagamento::create([
                'pedido_id' => $pedido->id, 'idempotency_key' => (string) Str::uuid(), 'requisicao_hash' => $hash,
                'dados_envio' => [
                    'type' => 'online', 'processing_mode' => 'automatic',
                    'external_reference' => (string) $pedido->id, 'total_amount' => $pedido->total,
                    'payer' => $this->pagador($pedido),
                    'transactions' => ['payments' => [['amount' => $pedido->total, 'payment_method' => $method]]],
                ],
            ]);
        });
        $this->enfileirar($pagamento);

        return $pagamento;
    }

    private function enfileirar(PedidoPagamento $pagamento): void
    {
        try {
            ProcessarPedidoPagamento::dispatch($pagamento->id)->afterCommit();
        } catch (\Throwable) {
            // O registro durável será retomado pelo scheduler se o Redis estiver indisponível.
            \Illuminate\Support\Facades\Log::warning('Publicação do pagamento pendente.', ['pagamento_id' => $pagamento->id]);
        }
    }

    public function retomarPendentes(): int
    {
        $total = 0;
        PedidoPagamento::query()->whereIn('processamento', ['pendente', 'aguardando', 'processando'])
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->eachById(function (PedidoPagamento $pagamento) use (&$total): void {
                $this->enfileirar($pagamento);
                $total++;
            });

        return $total;
    }

    public function processar(int $pagamentoId): PedidoPagamento
    {
        $pagamento = PedidoPagamento::findOrFail($pagamentoId);
        if (in_array($pagamento->processamento, ['concluido', 'verificacao_necessaria'], true)) {
            return $pagamento;
        }
        if ($pagamento->order_id) {
            return $this->sincronizar($pagamento->order_id);
        }
        // Tentativas antigas, sem snapshot, precisam de verificação, nunca de outra cobrança.
        if (! $pagamento->dados_envio || $pagamento->created_at->lte(now()->subMinutes(10))) {
            $pagamento->update(['processamento' => 'verificacao_necessaria', 'dados_envio' => null]);
            return $pagamento;
        }
        $pagamento->update(['processamento' => 'processando']);
        $order = $this->chamar('post', '/v1/orders', $pagamento->dados_envio, $pagamento->idempotency_key);
        abort_unless(is_string($order['id'] ?? null) && preg_match('/^[a-zA-Z0-9]+$/D', $order['id']), 502, 'Resposta inválida do provedor.');

        // Conserva o identificador antes da consulta: uma falha de rede não pode perder a cobrança criada.
        DB::transaction(function () use ($pagamento, $order): void {
            $registro = PedidoPagamento::query()->lockForUpdate()->findOrFail($pagamento->id);
            abort_if($registro->order_id && $registro->order_id !== $order['id'], 409, 'Cobrança incompatível com o pedido.');
            $registro->update(['order_id' => $order['id'], 'dados_envio' => null, 'processamento' => 'aguardando']);
        });

        // Confirma o recurso na API, inclusive para respostas imediatamente aprovadas.
        return $this->sincronizar($order['id']);
    }

    public function consultar(Pedido $pedido): PedidoPagamento
    {
        $pagamento = PedidoPagamento::where('pedido_id', $pedido->id)->firstOrFail();

        return $pagamento;
    }

    public function sincronizar(string $orderId): PedidoPagamento
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]+$/D', $orderId), 422);
        $order = $this->chamar('get', '/v1/orders/'.$orderId);

        return DB::transaction(function () use ($order, $orderId): PedidoPagamento {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($order['external_reference'] ?? null);
            $pagamento = PedidoPagamento::where('pedido_id', $pedido->id)->lockForUpdate()->firstOrFail();
            abort_unless(($order['id'] ?? null) === $orderId
                && ($order['external_reference'] ?? null) === (string) $pedido->id
                && $this->moedaCompativel($order)
                && $this->centavos($order['total_amount'] ?? '') === $this->centavos($pedido->total)
                && (! $pagamento->order_id || $pagamento->order_id === $orderId), 409, 'Cobrança incompatível com o pedido.');
            $instrucoes = data_get($order, 'transactions.payments.0.payment_method', []);
            $pagamento->update([
                'order_id' => $orderId, 'status' => $order['status'] ?? 'desconhecido',
                'dados_envio' => null,
                'processamento' => in_array($order['status'] ?? '', ['processed', 'failed', 'canceled', 'cancelled', 'expired', 'refunded', 'charged_back'], true) ? 'concluido' : 'aguardando',
                'instrucoes' => array_intersect_key($instrucoes, array_flip(['qr_code', 'qr_code_base64', 'ticket_url'])),
            ]);
            if (($order['status'] ?? '') === 'processed' && ($order['status_detail'] ?? '') === 'accredited'
                && $this->centavos($order['total_paid_amount'] ?? '') === $this->centavos($pedido->total)) {
                $this->pedidos->registrarPagamentoConfirmado($pedido);
            }

            return $pagamento;
        });
    }

    private function centavos(mixed $valor): int
    {
        abort_unless(is_string($valor) && preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $valor), 409, 'Valor inválido do provedor.');
        [$inteiro, $decimal] = array_pad(explode('.', $valor), 2, '');

        return (int) $inteiro * 100 + (int) str_pad($decimal, 2, '0');
    }

    private function pagador(Pedido $pedido): array
    {
        $pagador = ['email' => $pedido->cliente->usuario->email];
        $documento = preg_replace('/\D/', '', (string) ($pedido->processamentoFiscal?->dados_cliente['documento'] ?? ''));
        if (in_array(strlen($documento), [11, 14], true)) {
            $pagador['identification'] = ['type' => strlen($documento) === 11 ? 'CPF' : 'CNPJ', 'number' => $documento];
        }

        return $pagador;
    }

    private function moedaCompativel(array $order): bool
    {
        // Orders brasileiras documentam country_code=BRA; versões que informem moeda devem concordar.
        if (isset($order['country_code']) && $order['country_code'] !== 'BRA') return false;
        foreach (['currency_id', 'currency'] as $campo) {
            if (isset($order[$campo]) && $order[$campo] !== 'BRL') return false;
        }

        return ($order['country_code'] ?? null) === 'BRA'
            || ($order['currency_id'] ?? $order['currency'] ?? null) === 'BRL';
    }

    private function credencial(): string
    {
        $token = trim((string) config('mercadopago.access_token'));
        abort_unless(is_string($token) && $token !== '', 503, 'Pagamento temporariamente indisponível.');

        return $token;
    }

    private function chamar(string $metodo, string $path, array $dados = [], ?string $key = null): array
    {
        $http = Http::withToken($this->credencial())->acceptJson()->connectTimeout(5)->timeout(config('mercadopago.timeout'));
        if ($key) {
            $http = $http->withHeaders(['X-Idempotency-Key' => $key]);
        }
        try {
            $response = $http->{$metodo}('https://api.mercadopago.com'.$path, $dados);
        } catch (ConnectionException) {
            abort(503, 'Provedor indisponível. Tente novamente com os mesmos dados.');
        }
        abort_unless($response->successful(), 502, 'Não foi possível processar o pagamento.');
        $json = $response->json();
        abort_unless(is_array($json), 502, 'Resposta inválida do provedor.');

        return $json;
    }
}
