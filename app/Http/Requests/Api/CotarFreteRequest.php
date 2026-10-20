<?php

namespace App\Http\Requests\Api;

class CotarFreteRequest extends ClientePedidoRequest
{
    public function rules(): array
    {
        return ['cep' => ['required', 'string', 'regex:/^\d{8}$/']];
    }
}
