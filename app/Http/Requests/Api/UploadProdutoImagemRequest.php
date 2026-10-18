<?php

namespace App\Http\Requests\Api;

class UploadProdutoImagemRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'principal' => ['sometimes', 'boolean'],
        ];
    }
}
