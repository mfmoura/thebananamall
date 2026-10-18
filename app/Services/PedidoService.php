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
        private readonly PedidoFiscalService $fiscalService,
    ) {}

    public function listar(Cliente $cliente): Collection
    {
        return Pedido::query()->with(['formaPagamento', 'itens.produto.imagens', 'processamentoFiscal', 'pagamento'])
            ->where('cliente_id', $cliente->id)
            ->latest('data')
            ->get();
    }

    public function buscar(Cliente $cliente, Pedido $pedido): Pedido
    {
        return $pedido->load(['formaPagamento', 'itens.produto.imagens', 'processamentoFiscal', 'pagamento']);
    }

    public function comprar(Cliente $cliente, int $formaPagamentoId, string $idempotencyKey, array $dadosFiscais = []): Pedido
    {
        return $this->carrinhoService->comBloqueioDeEstoque(function () use (
            $cliente,
            $formaPagamentoId,
            $idempotencyKey,
            $dadosFiscais,
        ): Pedido {
            $existente = $this->pedidoIdempotente($cliente, $idempotencyKey);

            if ($existente !== null) {
                return $existente;
            }

            $carrinho = $this->carrinhoService->consultar($cliente);

            if ($carrinho === null || $carrinho->itens === []) {
                throw ValidationException::withMessages(['carrinho' => 'O carrinho estÃ¡ vazio ou expirou.']);
            }

            $this->carrinhoService->validarParaCheckout($cliente, $carrinho);

            [$pedido, $criado] = DB::transaction(function () use ($cliente, $formaPagamentoId, $idempotencyKey, $carrinho, $dadosFiscais): array {
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
                        'forma_pagamento_id' => 'A forma de pagamento nÃ£o estÃ¡ disponÃ­vel.',
                    ]);
                }

                $codigos = array_map(static fn ($item): int => $item->codigo, $carrinho->itens);
                $produtos = Produto::query()->whereIn('id', $codigos)->where('ativo', true)
                    ->lockForUpdate()->get()->keyBy('id');

                if ($produtos->count() !== count(array_unique($codigos))) {
                    throw ValidationException::withMessages([
                        'carrinho' => 'Um ou mais produtos nÃ£o existem ou estÃ£o indisponÃ­veis.',
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
                    // O produto jÃ¡ estÃ¡ bloqueado; PrecoService usa o mesmo bloqueio ao publicar preÃ§os.
                    $precoAtual = $produto->precoAtual()->first();

                    if ($precoAtual === null) {
                        throw ValidationException::withMessages([
                            'carrinho' => "O produto {$produto->id} estÃƒÂ¡ sem preÃƒÂ§o vigente.",
                        ]);
                    }

                    $pedido->itens()->create([
                        'codigo' => $produto->id,
                        'configuracao_fiscal' => $produto->configuracao_fiscal,
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

                $clienteBloqueado->update(['dados_fiscais' => $dadosFiscais]);
                $this->fiscalService->acompanhar($pedido, $dadosFiscais);

                return [$pedido->load(['formaPagamento', 'itens.produto.imagens', 'processamentoFiscal', 'pagamento']), true];
            });

            if ($criado) {
                $this->carrinhoService->removerSobBloqueio($cliente);
            }

            return $pedido;
        });
    }

    public function cancelar(Cliente $cliente, Pedido $pedido): bool
    {
        return $this->carrinhoService->comBloqueioDeEstoque(fn (): bool => DB::transaction(function () use ($cliente, $pedido): bool {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);

            if ($pedido->cliente_id !== $cliente->id || $pedido->status !== StatusPedido::AguardandoPagamento) {
                return false;
            }

            // Uma cobrança criada ou com resultado incerto pode ser paga depois.
            // Não libere estoque sem cancelar a cobrança no provedor primeiro.
            if (\App\Models\PedidoPagamento::withTrashed()->where('pedido_id', $pedido->id)->exists()) {
                return false;
            }

            $this->movimentacaoService->estornarSaidasDoPedido($pedido);
            $pedido->processamentoFiscal()->update(['etapa' => 'cancelado']);
            $pedido->status = StatusPedido::Cancelado;

            return $pedido->save();
        }));
    }

    /** Chamada interna somente depois da confirmação verificada pelo gateway de pagamento. */
    public function registrarPagamentoConfirmado(Pedido $pedido): bool
    {
        return DB::transaction(function () use ($pedido): bool {
            $pedido = Pedido::query()->lockForUpdate()->findOrFail($pedido->id);
            if (in_array($pedido->status->value, PedidoFiscalService::PAGOS, true)) {
                return true;
            }
            if ($pedido->status !== StatusPedido::AguardandoPagamento) {
                return false;
            }

            return $pedido->update(['status' => StatusPedido::Pago]);
        });
    }

    private function pedidoIdempotente(Cliente $cliente, string $key): ?Pedido
    {
        return Pedido::query()->with(['formaPagamento', 'itens.produto.imagens', 'processamentoFiscal', 'pagamento'])
            ->where('cliente_id', $cliente->id)
            ->where('idempotency_key', $key)
            ->first();
    }
}
