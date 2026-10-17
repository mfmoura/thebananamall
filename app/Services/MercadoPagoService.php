<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Models\Pedido;
use App\Models\PedidoPagamento;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MercadoPagoService
{
    public function __construct(private readonly PedidoService $pedidos) {}

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
            abort_if($dados['meio'] === 'debit_card' && ($dados['parcelas'] ?? 1) !== 1, 422, 'Débito não permite parcelas.');
            $hash = hash_hmac('sha256', json_encode($dados, JSON_THROW_ON_ERROR), config('app.key'));
            $existente = PedidoPagamento::withTrashed()->where('pedido_id', $pedido->id)->first();
            if ($existente) {
                abort_unless(hash_equals($existente->requisicao_hash, $hash), 409, 'Já existe uma tentativa de pagamento para este pedido.');

                return $existente;
            }

            abort_unless($pedido->status === StatusPedido::AguardandoPagamento, 409, 'Pedido indisponível para pagamento.');

            return PedidoPagamento::create(['pedido_id' => $pedido->id, 'idempotency_key' => (string) Str::uuid(), 'requisicao_hash' => $hash]);
        });
        if ($pagamento->order_id) {
            return $this->sincronizar($pagamento->order_id);
        }
        $method = $dados['meio'] === 'pix'
            ? ['id' => 'pix', 'type' => 'bank_transfer']
            : ['id' => $dados['payment_method_id'], 'type' => $dados['meio'], 'token' => $dados['token'], 'installments' => (int) $dados['parcelas']];
        $order = $this->chamar('post', '/v1/orders', [
            'type' => 'online', 'processing_mode' => 'automatic',
            'external_reference' => (string) $pedido->id, 'total_amount' => $pedido->total,
            'payer' => ['email' => $pedido->cliente->usuario->email],
            'transactions' => ['payments' => [['amount' => $pedido->total, 'payment_method' => $method]]],
        ], $pagamento->idempotency_key);
        abort_unless(is_string($order['id'] ?? null) && preg_match('/^[a-zA-Z0-9]+$/D', $order['id']), 502, 'Resposta inválida do provedor.');

        // Confirma o recurso na API, inclusive para respostas imediatamente aprovadas.
        return $this->sincronizar($order['id']);
    }

    public function consultar(Pedido $pedido): PedidoPagamento
    {
        $pagamento = PedidoPagamento::where('pedido_id', $pedido->id)->firstOrFail();

        return $pagamento->order_id ? $this->sincronizar($pagamento->order_id) : $pagamento;
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
                && ($order['currency_id'] ?? null) === 'BRL'
                && $this->centavos($order['total_amount'] ?? '') === $this->centavos($pedido->total)
                && (! $pagamento->order_id || $pagamento->order_id === $orderId), 409, 'Cobrança incompatível com o pedido.');
            $instrucoes = data_get($order, 'transactions.payments.0.payment_method', []);
            $pagamento->update([
                'order_id' => $orderId, 'status' => $order['status'] ?? 'desconhecido',
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

    private function credencial(): string
    {
        $token = config('mercadopago.access_token');
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
