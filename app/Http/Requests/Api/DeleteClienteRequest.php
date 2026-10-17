<?php

namespace App\Http\Requests\Api;

class DeleteClienteRequest extends OwnedClienteRequest
{
    public function authorize(): bool
    {
        return $this->podeAcessarCliente();
    }
}
