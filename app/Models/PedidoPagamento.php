<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PedidoPagamento extends Model
{
    use SoftDeletes;

    protected $table = 'pedido_pagamentos';

    protected $attributes = ['status' => 'criando', 'processamento' => 'pendente'];

    protected $fillable = ['pedido_id', 'idempotency_key', 'requisicao_hash', 'order_id', 'status', 'instrucoes', 'dados_envio', 'processamento'];

    protected $hidden = ['idempotency_key', 'requisicao_hash', 'dados_envio'];

    protected function casts(): array
    {
        return ['instrucoes' => 'encrypted:array', 'dados_envio' => 'encrypted:array'];
    }
}
