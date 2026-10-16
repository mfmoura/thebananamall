<?php

namespace App\Models;

use App\Enums\StatusPedido;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'cliente_id',
        'forma_pagamento_id',
        'status',
        'data',
        'idempotency_key',
    ];

    protected $hidden = ['idempotency_key'];

    protected $appends = ['total'];

    protected function casts(): array
    {
        return [
            'status' => StatusPedido::class,
            'data' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function formaPagamento(): BelongsTo
    {
        return $this->belongsTo(FormaPagamento::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function notaEstoque(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NotaEstoque::class);
    }

    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }

    public function getTotalAttribute(): string
    {
        $centavos = $this->itens->sum(
            static fn (PedidoItem $item): int => (int) round(
                (float) $item->preco * (float) $item->quantidade * 100,
            ),
        );

        return number_format($centavos / 100, 2, '.', '');
    }
}
