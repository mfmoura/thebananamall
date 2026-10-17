<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProdutoService
{
    public function __construct(
        private readonly ProdutoMovimentacaoService $movimentacaoService,
        private readonly CarrinhoService $carrinhoService,
    ) {}

    public function listar(?int $porPagina = null, int $pagina = 1): Collection|\Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->carrinhoService->comBloqueioDeEstoque(function () use ($porPagina, $pagina) {
            $consulta = Produto::query()->where('ativo', true)->with(['imagens', 'precoAtual'])
            ->withSum([
                'movimentacoes as quantidade_entrada' => fn ($query) => $query->where('direcao', 'entrada'),
            ], 'quantidade')
            ->withSum([
                'movimentacoes as quantidade_saida' => fn ($query) => $query->where('direcao', 'saida'),
            ], 'quantidade')
            ->orderBy('nome')->orderBy('id');
            $resultado = $porPagina === null ? $consulta->get() : $consulta->paginate($porPagina, ['*'], 'page', $pagina)->withQueryString();
            $produtos = $resultado instanceof Collection ? $resultado : $resultado->getCollection();
            $produtos->each(function (Produto $produto): void {
                $estoqueContado = (float) $produto->quantidade_entrada - (float) $produto->quantidade_saida;
                $produto->setAttribute('estoque_contado', round($estoqueContado, 3));
                $produto->setAttribute(
                    'estoque',
                    round($estoqueContado - $this->movimentacaoService->contarReservado($produto->id), 3),
                );
                $produto->makeHidden(['quantidade_entrada', 'quantidade_saida']);
            });

            return $resultado;
        });
    }

    public function consultar(Produto $produto): Produto
    {
        if (! $produto->ativo) {
            throw (new ModelNotFoundException())->setModel(Produto::class, [$produto->id]);
        }

        return $this->carrinhoService->comBloqueioDeEstoque(function () use ($produto): Produto {
            $estoqueContado = $this->contarEstoque($produto);

            return $produto->load(['imagens', 'precoAtual'])
                ->setAttribute('estoque_contado', $estoqueContado)
                ->setAttribute('estoque', round($estoqueContado - $this->movimentacaoService->contarReservado($produto->id), 3));
        });
    }

    public function criar(array $dados): Produto
    {
        $produto = DB::transaction(function () use ($dados): Produto {
            $preco = $dados['preco'];
            unset($dados['preco']);

            $produto = Produto::query()->create(array_merge($dados, [
                'ativo' => $dados['ativo'] ?? true,
            ]));
            $produto->precos()->create(['preco' => $preco, 'data' => now()]);

            return $produto->load(['imagens', 'precoAtual']);
        });

        return $produto->setAttribute('estoque_contado', 0.0)->setAttribute('estoque', 0.0);
    }

    public function atualizar(Produto $produto, array $dados): Produto
    {
        unset($dados['preco']);
        $produto->update($dados);

        $estoqueContado = $this->contarEstoque($produto);

        return $produto->refresh()->load(['imagens', 'precoAtual'])
            ->setAttribute('estoque_contado', $estoqueContado)
            ->setAttribute('estoque', round($estoqueContado - $this->movimentacaoService->contarReservado($produto->id), 3));
    }

    /** Desativação preserva referências históricas de pedidos e movimentações. */
    public function excluir(Produto $produto): bool
    {
        $produto->ativo = false;

        return $produto->save();
    }

    public function contarEstoque(Produto $produto): float
    {
        return $this->movimentacaoService->contarEstoque($produto);
    }

    public function contarDisponivel(Produto $produto): float
    {
        return $this->movimentacaoService->contarDisponivel($produto);
    }

    public function movimentacoes(): Collection
    {
        return ProdutoMovimentacao::query()->with(['produto', 'nota', 'movimentoOriginal'])
            ->latest('data')->get();
    }
}
