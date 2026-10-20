<?php

namespace App\Http\Requests\Api;

class StoreCarrinhoItemRequest extends CarrinhoRequest
{
    public function rules(): array
    {
        return [
            'tamanho' => ['sometimes', 'nullable', 'string', 'max:30'],
            'codigo' => ['required', 'integer', 'min:1'],
            'quantidade' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }
}
