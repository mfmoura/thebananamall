<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class ProdutoService
{
    public function consultarAdministracao(Produto $produto): Produto
    {
        return $produto->load(['imagens', 'precoAtual', 'categoriaCadastro']);
    }

    public function listarAdministracao(array $filtros): LengthAwarePaginator
    {
        $query = Produto::query()->with(['imagens', 'precoAtual', 'categoriaCadastro'])->orderBy('nome')->orderBy('id');
        if (! empty($filtros['categoria'])) $query->where('categoria', $filtros['categoria']);
        $termo = trim($filtros['busca'] ?? '');
        if ($termo !== '') {
            $padrao = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termo).'%';
            $query->where(fn ($q) => $q->whereRaw("lower(nome) like lower(?) escape '!'", [$padrao])
                ->orWhereRaw("lower(codigo) like lower(?) escape '!'", [$padrao]));
        }

        return $query->paginate((int) ($filtros['per_page'] ?? 10), ['*'], 'page', (int) ($filtros['page'] ?? 1));
    }

    public function __construct(
        private readonly ProdutoMovimentacaoService $movimentacaoService,
        private readonly CarrinhoService $carrinhoService,
        private readonly GradeProdutoService $grades,
        private readonly ReservaEstoqueService $reservas,
    ) {}

    public function listar(?int $porPagina = null, int $pagina = 1, array $filtros = []): Collection|\Illuminate\Pagination\LengthAwarePaginator
    {
        return $this->carrinhoService->comBloqueioDeEstoque(function () use ($porPagina, $pagina, $filtros) {
            $consulta = Produto::query()->where('ativo', true)->with(['imagens', 'precoAtual', 'categoriaCadastro'])
            ->withSum([
                'movimentacoes as quantidade_entrada' => fn ($query) => $query->withTrashed()->where('direcao', 'entrada'),
            ], 'quantidade')
            ->withSum([
                'movimentacoes as quantidade_saida' => fn ($query) => $query->withTrashed()->where('direcao', 'saida'),
            ], 'quantidade')
            ->orderBy('nome')->orderBy('id');
            if (! empty($filtros['categoria'])) {
                $consulta->where('categoria', $filtros['categoria']);
            }
            $termo = trim($filtros['busca'] ?? '');
            if ($termo !== '') {
                $padrao = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $termo).'%';
                $consulta->where(function ($query) use ($padrao) {
                    foreach (['nome', 'codigo', 'descricao'] as $campo) {
                        $query->orWhereRaw("lower(".$campo.") like lower(?) escape '!'", [$padrao]);
                    }
                });
            }
            $produtos = $consulta->get();
            $produtos->each(function (Produto $produto): void {
                $this->comEstoquePorTamanho($produto);
                $estoqueContado = (float) $produto->quantidade_entrada - (float) $produto->quantidade_saida;
                $produto->setAttribute('estoque_contado', round($estoqueContado, 3));
                $produto->setAttribute(
                    'estoque',
                    round($estoqueContado - $this->movimentacaoService->contarReservado($produto->id), 3),
                );
                $produto->makeHidden(['quantidade_entrada', 'quantidade_saida']);
            });

            $produtos = $produtos->filter(static fn (Produto $produto): bool => (float) $produto->estoque > 0)->values();
            if ($porPagina === null) {
                return $produtos;
            }

            $porPagina = max(1, min(100, $porPagina));
            $pagina = max(1, $pagina);
            $resultado = new LengthAwarePaginator(
                $produtos->forPage($pagina, $porPagina)->values(),
                $produtos->count(),
                $porPagina,
                $pagina,
                ['path' => request()->url(), 'query' => request()->query()],
            );
            $resultado->withQueryString();

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

            return $this->comEstoquePorTamanho($produto->load(['imagens', 'precoAtual', 'categoriaCadastro']))
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

            return $produto->load(['imagens', 'precoAtual', 'categoriaCadastro']);
        });

        return $produto->setAttribute('estoque_contado', 0.0)->setAttribute('estoque', 0.0);
    }

    public function atualizar(Produto $produto, array $dados): Produto
    {
        unset($dados['preco']);
        return $this->carrinhoService->comBloqueioDeEstoque(fn () => DB::transaction(function () use ($produto, $dados): Produto {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produto->id);
            if (array_key_exists('grade', $dados)) {
                $this->grades->validarAlteracao($produto, $dados['grade']);
            }
            $produto->update($dados);

            $estoqueContado = $this->contarEstoque($produto);

            return $this->comEstoquePorTamanho($produto->refresh()->load(['imagens', 'precoAtual', 'categoriaCadastro']))
                ->setAttribute('estoque_contado', $estoqueContado)
                ->setAttribute('estoque', round($estoqueContado - $this->movimentacaoService->contarReservado($produto->id), 3));
        }));
    }

    /** Desativação preserva referências históricas de pedidos e movimentações. */
    public function excluir(Produto $produto): bool
    {
        $produto->ativo = false;

        $produto->save();
        return (bool) $produto->delete();
    }

    public function contarEstoque(Produto $produto): float
    {
        return $this->movimentacaoService->contarEstoque($produto);
    }

    public function contarDisponivel(Produto $produto): float
    {
        return $this->movimentacaoService->contarDisponivel($produto);
    }

    public function consultarLogistica(Produto $produto): Produto
    {
        return $this->carrinhoService->comBloqueioDeEstoque(function () use ($produto): Produto {
            $contado = $this->contarEstoque($produto);
            return $this->comEstoquePorTamanho($produto)->setAttribute('estoque_contado', $contado)
                ->setAttribute('estoque', round($contado - $this->movimentacaoService->contarReservado($produto->id), 3));
        });
    }

    public function historicoEstoque(array $dados): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return ProdutoMovimentacao::query()->with(['nota', 'movimentoOriginal'])
            ->where('produto_id', $dados['produto_id'])
            ->orderByDesc('data')->orderByDesc('id')->paginate($dados['per_page'] ?? 10);
    }

    public function movimentacoes(): Collection
    {
        return ProdutoMovimentacao::query()->with(['produto', 'nota', 'movimentoOriginal'])
            ->latest('data')->get();
    }

    private function comEstoquePorTamanho(Produto $produto): Produto
    {
        return $produto->setAttribute('estoque_por_tamanho', $this->reservas->resumoPorTamanho($produto));
    }
}
