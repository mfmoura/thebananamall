<?php

namespace App\Http\Requests\Api;

class UpdateCarrinhoRequest extends CarrinhoRequest
{
    public function rules(): array
    {
        return [
            'itens' => ['required', 'array', 'max:100'],
            'itens.*.codigo' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
