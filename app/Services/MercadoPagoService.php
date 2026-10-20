<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Jobs\ProcessarPedidoPagamento;
use App\Models\Pedido;
use App\Models\PedidoPagamento;
use App\Webservices\MercadoPagoClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MercadoPagoService
{
    public function __construct(
        private readonly PedidoService $pedidos,
        private readonly MercadoPagoClient $client,
    ) {}

    public function configuracaoPublica(): array
    {
        $this->client->verificarConfiguracao();
        $publicKey = trim((string) config('mercadopago.public_key'));
        abort_if($publicKey === '', 503, 'Pagamento temporariamente indisponível.');

        return ['public_key' => $publicKey, 'parcelas' => 1];
    }

    public function criar(Pedido $pedido, array $dados): PedidoPagamento
    {
        $this->client->verificarConfiguracao();
        // Persiste a chave ANTES da chamada: um timeout deve reutilizar a mesma cobrança.
        $pagamento = DB::transaction(function () use ($pedido, $dados): PedidoPagamento {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);
            $codigo = $pedido->formaPagamento->codigo;
            $esperado = match ($dados['meio']) {
                'pix' => 'pix', 'boleto' => 'boleto', 'credit_card' => 'cartao_credito', 'debit_card' => 'cartao_debito',
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

            $method = match ($dados['meio']) {
                'pix' => ['id' => 'pix', 'type' => 'bank_transfer'],
                'boleto' => ['id' => 'boleto', 'type' => 'ticket'],
                default => ['id' => $dados['payment_method_id'], 'type' => $dados['meio'], 'token' => $dados['token'], 'installments' => (int) $dados['parcelas']],
            };

            return PedidoPagamento::create([
                'pedido_id' => $pedido->id, 'idempotency_key' => (string) Str::uuid(), 'requisicao_hash' => $hash,
                'dados_envio' => [
                    'type' => 'online', 'processing_mode' => 'automatic',
                    'external_reference' => (string) $pedido->id, 'total_amount' => $pedido->total,
                    'payer' => $dados['meio'] === 'boleto' ? $this->pagadorBoleto($pedido) : $this->pagador($pedido),
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
        $order = $this->client->criarOrder($pagamento->dados_envio, $pagamento->idempotency_key);
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
        $order = $this->client->consultarOrder($orderId);

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
                'instrucoes' => [
                    ...array_intersect_key($pagamento->instrucoes ?? [], array_flip(['criado_em', 'expira_em'])),
                    ...array_intersect_key($instrucoes, array_flip(['qr_code', 'qr_code_base64', 'ticket_url', 'digitable_line', 'barcode_content'])),
                    ...array_filter([
                        'criado_em' => $order['created_date'] ?? null,
                        'expira_em' => data_get($order, 'transactions.payments.0.date_of_expiration'),
                    ], fn ($valor) => is_string($valor) && $valor !== ''),
                ],
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
        $dados = $pedido->processamentoFiscal?->dados_cliente ?? [];
        $nome = trim((string) ($dados['nome'] ?? ''));
        if ($nome !== '') {
            [$primeiro, $sobrenome] = array_pad(preg_split('/\s+/u', $nome, 2), 2, null);
            $pagador['first_name'] = $primeiro;
            if ($sobrenome !== null) $pagador['last_name'] = $sobrenome;
        }
        $documento = preg_replace('/\D/', '', (string) ($dados['documento'] ?? ''));
        if (in_array(strlen($documento), [11, 14], true)) {
            $pagador['identification'] = ['type' => strlen($documento) === 11 ? 'CPF' : 'CNPJ', 'number' => $documento];
        }

        return $pagador;
    }

    private function pagadorBoleto(Pedido $pedido): array
    {
        // Usa o snapshot confirmado no checkout, nunca dados arbitrários da requisição de pagamento.
        $dados = $pedido->processamentoFiscal?->dados_cliente ?? [];
        $dados = \Illuminate\Support\Facades\Validator::make($dados, [
            'nome' => ['required', 'string', 'max:60', 'regex:/^\S+\s+\S+/u'],
            'documento' => ['required', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            'endereco.logradouro' => ['required', 'string', 'max:60'],
            'endereco.numero' => ['required', 'string', 'max:60'],
            'endereco.bairro' => ['required', 'string', 'max:60'],
            'endereco.cidade' => ['required', 'string', 'max:60'],
            'endereco.estado' => ['required', \Illuminate\Validation\Rule::in(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'])],
            'endereco.cep' => ['required', 'string', 'regex:/^\d{8}$/'],
        ], ['required' => 'O cadastro do pedido está incompleto para gerar boleto.',
            'nome.regex' => 'Informe nome e sobrenome ou razão social completa no checkout.'])->validate();
        [$nome, $sobrenome] = preg_split('/\s+/u', trim($dados['nome']), 2);
        $endereco = $dados['endereco'];

        return [...$this->pagador($pedido), 'first_name' => $nome, 'last_name' => $sobrenome,
            'address' => ['street_name' => $endereco['logradouro'], 'street_number' => $endereco['numero'],
                'zip_code' => $endereco['cep'], 'neighborhood' => $endereco['bairro'],
                'city' => $endereco['cidade'], 'state' => $endereco['estado']]];
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
}
