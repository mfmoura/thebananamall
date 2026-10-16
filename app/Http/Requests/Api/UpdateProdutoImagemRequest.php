<?php

namespace App\Http\Requests\Api;

class UpdateProdutoImagemRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'path' => ['sometimes', 'required', 'url', 'max:2048'],
            'principal' => ['sometimes', 'boolean'],
        ];
    }
}
