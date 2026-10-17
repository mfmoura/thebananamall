<?php

namespace App\Services;

use App\Models\PedidoItem;
use App\Models\Preco;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PrecoService
{
    public function historico(Produto $produto): Collection
    {
        return $produto->precos()->latest('data')->latest('id')->get();
    }

    public function criar(Produto $produto, string $valor): Preco
    {
        return DB::transaction(function () use ($produto, $valor): Preco {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produto->id);

            return $produto->precos()->create([
                'preco' => $valor,
                'data' => now(),
            ]);
        });
    }

    public function excluir(Preco $preco): bool
    {
        return DB::transaction(function () use ($preco): bool {
            $produto = Produto::query()->lockForUpdate()->findOrFail($preco->produto_id);
            $preco = Preco::query()->lockForUpdate()->findOrFail($preco->id);
            $precoAtualId = $produto->precos()->orderByDesc('data')->orderByDesc('id')->value('id');

            if (
                (int) $precoAtualId === $preco->id
                || PedidoItem::withTrashed()->where('preco_id', $preco->id)->exists()
            ) {
                return false;
            }

            return (bool) $preco->delete();
        });
    }
}
