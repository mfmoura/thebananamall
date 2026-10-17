<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\ProdutoImagem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ProdutoImagemService
{
    public function criar(Produto $produto, array $dados): ProdutoImagem
    {
        return DB::transaction(function () use ($produto, $dados): ProdutoImagem {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produto->id);
            $principal = (bool) ($dados['principal'] ?? false) || ! $produto->imagens()->where('principal', true)->exists();

            if ($principal) {
                $produto->imagens()->update(['principal' => false]);
            }

            return $produto->imagens()->create(array_merge($dados, ['principal' => $principal]));
        });
    }

    public function atualizar(Produto $produto, ProdutoImagem $imagem, array $dados): ProdutoImagem
    {
        $this->garantirPertencimento($produto, $imagem);

        return DB::transaction(function () use ($produto, $imagem, $dados): ProdutoImagem {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produto->id);
            if ((bool) ($dados['principal'] ?? false)) {
                $produto->imagens()->where('id', '<>', $imagem->id)->update(['principal' => false]);
            }

            $imagem->update($dados);

            if (! $produto->imagens()->where('principal', true)->exists()) {
                $produto->imagens()->orderBy('id')->first()?->update(['principal' => true]);
            }

            return $imagem->refresh();
        });
    }

    public function excluir(Produto $produto, ProdutoImagem $imagem): bool
    {
        $this->garantirPertencimento($produto, $imagem);

        return DB::transaction(function () use ($produto, $imagem): bool {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produto->id);
            $excluida = (bool) $imagem->delete();
            if (! $produto->imagens()->where('principal', true)->exists()) {
                $produto->imagens()->orderBy('id')->first()?->update(['principal' => true]);
            }

            return $excluida;
        });
    }

    private function garantirPertencimento(Produto $produto, ProdutoImagem $imagem): void
    {
        if ($imagem->produto_id !== $produto->id) {
            throw (new ModelNotFoundException())->setModel(ProdutoImagem::class, [$imagem->id]);
        }
    }
}
