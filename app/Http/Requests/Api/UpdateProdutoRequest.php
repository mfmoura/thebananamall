<?php

namespace App\Http\Requests\Api;

use App\Enums\CategoriaProduto;
use App\Models\Produto;
use Illuminate\Validation\Rule;

class UpdateProdutoRequest extends AdministradorRequest
{
    public function rules(): array
    {
        /** @var Produto $produto */
        $produto = $this->route('produto');

        return [
            'codigo' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('produtos', 'codigo')->ignore($produto)],
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'categoria' => ['sometimes', 'required', Rule::enum(CategoriaProduto::class)],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
