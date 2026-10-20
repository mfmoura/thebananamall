<?php

namespace App\Http\Requests\Api;

class DeleteCarrinhoItemRequest extends CarrinhoRequest
{
    public function rules(): array
    {
        return ['tamanho' => ['sometimes', 'nullable', 'string', 'max:30']];
    }
}
