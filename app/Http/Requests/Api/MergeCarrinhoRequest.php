<?php

namespace App\Http\Requests\Api;

class MergeCarrinhoRequest extends ClientePedidoRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['visitante_token' => $this->header('X-Visitante-Token')]);
    }

    public function rules(): array
    {
        return ['visitante_token' => ['required', 'string', 'size:64']];
    }
}
