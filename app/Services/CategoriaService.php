<?php

namespace App\Services;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Collection;

class CategoriaService
{
    public function criar(array $dados): Categoria
    {
        return Categoria::create($dados);
    }

    public function atualizar(Categoria $categoria, array $dados): Categoria
    {
        $categoria->update($dados);

        return $categoria->refresh();
    }

    public function excluir(Categoria $categoria): void
    {
        $categoria->delete();
    }

    public function listar(): Collection
    {
        return Categoria::query()->orderBy('nome')->get(['id', 'nome_codigo', 'nome']);
    }
}
