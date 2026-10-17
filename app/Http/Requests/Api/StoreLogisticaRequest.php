<?php

namespace App\Http\Requests\Api;

class StoreLogisticaRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('users', 'login')->withoutTrashed()],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->withoutTrashed()->where('email_validado', true)],
            'password' => ['required', 'string', 'min:8', 'max:15', 'confirmed'],
            'nome' => ['required', 'string', 'max:255'],
            'webhook' => ['required', 'url', 'max:2048'],
        ];
    }
}
