<?php

namespace App\Http\Requests\Api;

use App\Enums\CategoriaProduto;
use Illuminate\Validation\Rule;

class StoreProdutoRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:80', 'unique:produtos,codigo'],
            'nome' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::enum(CategoriaProduto::class)],
            'preco' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
