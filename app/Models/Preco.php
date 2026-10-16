<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Preco extends Model
{
    protected $table = 'precos';

    protected $fillable = ['produto_id', 'preco', 'data'];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'data' => 'datetime',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function itensPedido(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }
}
