<?php

namespace App\Http\Requests\Api;

class UpdateCarrinhoItemRequest extends CarrinhoRequest
{
    public function rules(): array
    {
        return [
            'quantidade' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
