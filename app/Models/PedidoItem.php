<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoItem extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'pedido_itens';

    protected $fillable = [
        'pedido_id',
        'configuracao_fiscal',
        'codigo',
        'preco_id',
        'preco',
        'quantidade',
        'data',
    ];

    protected function casts(): array
    {
        return [
            'configuracao_fiscal' => 'array',
            'preco' => 'decimal:2',
            'quantidade' => 'decimal:3',
            'data' => 'date:Y-m-d',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class)->withTrashed();
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'codigo')->withTrashed();
    }

    public function precoOriginal(): BelongsTo
    {
        return $this->belongsTo(Preco::class, 'preco_id')->withTrashed();
    }

    public function alterarQuantidade(float $quantidade): bool
    {
        if (! is_finite($quantidade) || $quantidade <= 0) {
            return false;
        }

        $this->quantidade = $quantidade;
        $this->data = now()->toDateString();

        return $this->save();
    }
}
