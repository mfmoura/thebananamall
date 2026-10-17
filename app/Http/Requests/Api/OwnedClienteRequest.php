<?php

namespace App\Http\Requests\Api;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

abstract class OwnedClienteRequest extends FormRequest
{
    protected function podeAcessarCliente(): bool
    {
        $cliente = $this->route('cliente');
        $usuario = $this->user();

        return $cliente instanceof Cliente
            && ($usuario?->is($cliente->usuario) === true || $usuario?->administrador()->exists() === true);
    }
}
