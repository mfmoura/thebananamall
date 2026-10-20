<?php

namespace App\Http\Requests\Api;

class UpdateCarrinhoRequest extends CarrinhoRequest
{
    public function rules(): array
    {
        return [
            'itens' => ['required', 'array', 'max:100'],
            'itens.*.codigo' => ['required', 'integer', 'min:1'],
            'itens.*.tamanho' => ['sometimes', 'nullable', 'string', 'max:30'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
