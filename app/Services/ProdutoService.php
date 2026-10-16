<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProdutoService
{
    public function __construct(private readonly ProdutoMovimentacaoService $movimentacaoService) {}

    public function listar(): Collection
    {
        return Produto::query()->where('ativo', true)->with(['imagens', 'precoAtual'])
            ->withSum([
                'movimentacoes as quantidade_entrada' => fn ($query) => $query->where('direcao', 'entrada'),
            ], 'quantidade')
            ->withSum([
                'movimentacoes as quantidade_saida' => fn ($query) => $query->where('direcao', 'saida'),
            ], 'quantidade')
            ->orderBy('nome')->get()->each(function (Produto $produto): void {
                $produto->setAttribute(
                    'estoque',
                    (float) $produto->quantidade_entrada - (float) $produto->quantidade_saida,
                );
                $produto->makeHidden(['quantidade_entrada', 'quantidade_saida']);
            });
    }

    public function consultar(Produto $produto): Produto
    {
        if (! $produto->ativo) {
            throw (new ModelNotFoundException())->setModel(Produto::class, [$produto->id]);
        }

        return $produto->load(['imagens', 'precoAtual'])->setAttribute('estoque', $this->contarEstoque($produto));
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

        return $produto->setAttribute('estoque', 0.0);
    }

    public function atualizar(Produto $produto, array $dados): Produto
    {
        unset($dados['preco']);
        $produto->update($dados);

        return $produto->refresh()->load(['imagens', 'precoAtual'])->setAttribute('estoque', $this->contarEstoque($produto));
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

    public function movimentacoes(): Collection
    {
        return ProdutoMovimentacao::query()->with(['produto', 'nota', 'movimentoOriginal'])
            ->latest('data')->get();
    }
}
