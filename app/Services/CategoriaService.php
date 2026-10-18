<?php

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Collection;

class CategoriaService
{
    public function listar(): Collection
    {
        return Categoria::query()->orderBy('nome')->get(['id', 'nome_codigo', 'nome']);
    }
}
