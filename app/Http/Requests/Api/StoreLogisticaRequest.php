<?php

namespace App\Http\Requests\Api;

class StoreLogisticaRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255', 'unique:users,login'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:15', 'max:64', 'confirmed'],
            'nome' => ['required', 'string', 'max:255'],
            'webhook' => ['required', 'url', 'max:2048'],
        ];
    }
}
