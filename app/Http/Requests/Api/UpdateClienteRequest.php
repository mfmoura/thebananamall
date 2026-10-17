<?php

namespace App\Http\Requests\Api;

use App\Models\Cliente;
use Illuminate\Validation\Rule;

class UpdateClienteRequest extends OwnedClienteRequest
{
    public function authorize(): bool
    {
        return $this->podeAcessarCliente();
    }

    public function rules(): array
    {
        /** @var Cliente $cliente */
        $cliente = $this->route('cliente');

        return [
            'login' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'login')->withoutTrashed()->ignore($cliente->usuario_id)],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:15', 'confirmed'],
            'email' => ['prohibited'],
        ];
    }
}
