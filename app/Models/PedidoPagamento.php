<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoPagamento extends Model
{
    use SoftDeletes;

    protected $table = 'pedido_pagamentos';

    protected $fillable = ['pedido_id', 'idempotency_key', 'requisicao_hash', 'order_id', 'status', 'instrucoes'];

    protected $hidden = ['idempotency_key', 'requisicao_hash'];

    protected function casts(): array
    {
        return ['instrucoes' => 'encrypted:array'];
    }
}
