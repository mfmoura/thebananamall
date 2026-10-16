<?php

namespace App\Http\Requests\Api;

class StorePrecoRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'preco' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ];
    }
}
