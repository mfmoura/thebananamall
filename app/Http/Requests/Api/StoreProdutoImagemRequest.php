<?php

namespace App\Http\Requests\Api;

class StoreProdutoImagemRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'path' => ['required', 'url', 'max:2048'],
            'principal' => ['sometimes', 'boolean'],
        ];
    }
}
