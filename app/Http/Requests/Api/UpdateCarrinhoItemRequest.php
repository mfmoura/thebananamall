<?php

namespace App\Http\Requests\Api;

class UpdateCarrinhoItemRequest extends CarrinhoRequest
{
    public function rules(): array
    {
        return [
            'tamanho' => ['sometimes', 'nullable', 'string', 'max:30'],
            'quantidade' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
