<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormaPagamento extends Model
{
    protected $table = 'formas_pagamento';

    protected $fillable = ['codigo', 'nome', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }
}
