<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Models\Cliente;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoService
{
    public function __construct(
        private readonly CarrinhoService $carrinhoService,
        private readonly ProdutoMovimentacaoService $movimentacaoService,
    ) {}

    public function listar(Cliente $cliente): Collection
    {
        return Pedido::query()->with(['formaPagamento', 'itens'])
            ->where('cliente_id', $cliente->id)
            ->latest('data')
            ->get();
    }

    public function buscar(Cliente $cliente, Pedido $pedido): Pedido
    {
        return $pedido->load(['formaPagamento', 'itens']);
    }

    public function comprar(Cliente $cliente, int $formaPagamentoId, string $idempotencyKey): Pedido
    {
        $existente = $this->pedidoIdempotente($cliente, $idempotencyKey);

        if ($existente !== null) {
            return $existente;
        }

        $carrinho = $this->carrinhoService->consultar($cliente);

        if ($carrinho === null || $carrinho->itens === []) {
            throw ValidationException::withMessages(['carrinho' => 'O carrinho está vazio ou expirou.']);
        }

        [$pedido, $criado] = DB::transaction(function () use ($cliente, $formaPagamentoId, $idempotencyKey, $carrinho): array {
            $clienteBloqueado = Cliente::query()->lockForUpdate()->findOrFail($cliente->id);
            $existente = $this->pedidoIdempotente($clienteBloqueado, $idempotencyKey);

            if ($existente !== null) {
                return [$existente, false];
            }

            $formaPagamento = FormaPagamento::query()
                ->whereKey($formaPagamentoId)
                ->where('ativo', true)
                ->first();

            if ($formaPagamento === null) {
                throw ValidationException::withMessages([
                    'forma_pagamento_id' => 'A forma de pagamento não está disponível.',
                ]);
            }

            $codigos = array_map(static fn ($item): int => $item->codigo, $carrinho->itens);
            $produtos = Produto::query()->whereIn('id', $codigos)->where('ativo', true)
                ->lockForUpdate()->get()->keyBy('id');

            if ($produtos->count() !== count(array_unique($codigos))) {
                throw ValidationException::withMessages([
                    'carrinho' => 'Um ou mais produtos não existem ou estão indisponíveis.',
                ]);
            }

            $pedido = Pedido::query()->create([
                'cliente_id' => $clienteBloqueado->id,
                'forma_pagamento_id' => $formaPagamento->id,
                'status' => StatusPedido::AguardandoPagamento,
                'data' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($carrinho->itens as $item) {
                /** @var Produto $produto */
                $produto = $produtos->get($item->codigo);
                // O produto já está bloqueado; PrecoService usa o mesmo bloqueio ao publicar preços.
                $precoAtual = $produto->precoAtual()->first();

                if ($precoAtual === null) {
                    throw ValidationException::withMessages([
                        'carrinho' => "O produto {$produto->id} estÃ¡ sem preÃ§o vigente.",
                    ]);
                }

                $pedido->itens()->create([
                    'codigo' => $produto->id,
                    'preco_id' => $precoAtual->id,
                    'preco' => $precoAtual->preco,
                    'quantidade' => $item->quantidade(),
                    'data' => now()->toDateString(),
                ]);
                $this->movimentacaoService->registrarSaidaDoPedido(
                    $produto,
                    $pedido,
                    $item->quantidade(),
                );
            }

            return [$pedido->load(['formaPagamento', 'itens']), true];
        });

        if ($criado) {
            $this->carrinhoService->excluir($cliente);
        }

        return $pedido;
    }

    public function cancelar(Cliente $cliente, Pedido $pedido): bool
    {
        return DB::transaction(function () use ($cliente, $pedido): bool {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->cliente_id !== $cliente->id || $pedido->status !== StatusPedido::AguardandoPagamento) {
                return false;
            }

            $this->movimentacaoService->estornarSaidasDoPedido($pedido);
            $pedido->status = StatusPedido::Cancelado;

            return $pedido->save();
        });
    }

    private function pedidoIdempotente(Cliente $cliente, string $key): ?Pedido
    {
        return Pedido::query()->with(['formaPagamento', 'itens'])
            ->where('cliente_id', $cliente->id)
            ->where('idempotency_key', $key)
            ->first();
    }
}
