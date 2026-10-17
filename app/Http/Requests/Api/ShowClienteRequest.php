<?php

namespace App\Http\Requests\Api;

class ShowClienteRequest extends OwnedClienteRequest
{
    public function authorize(): bool
    {
        return $this->podeAcessarCliente();
    }
}
