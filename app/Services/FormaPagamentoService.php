<?php

namespace App\Services;

use App\Models\FormaPagamento;
use Illuminate\Database\Eloquent\Collection;

class FormaPagamentoService
{
    public function listarAtivas(): Collection
    {
        return FormaPagamento::query()->where('ativo', true)->orderBy('nome')->get(['id', 'codigo', 'nome']);
    }
}
