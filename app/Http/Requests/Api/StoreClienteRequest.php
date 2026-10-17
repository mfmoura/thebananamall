<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('users', 'login')->withoutTrashed()],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->withoutTrashed()->where('email_validado', true)],
            'password' => ['required', 'string', 'min:8', 'max:15', 'confirmed'],
            'tipo' => ['prohibited'],
            'perfil' => ['prohibited'],
            'endereco' => ['sometimes', 'nullable', 'array'],
            'endereco.logradouro' => ['required_with:endereco', 'string', 'max:255'],
            'endereco.numero' => ['required_with:endereco', 'string', 'max:30'],
            'endereco.complemento' => ['sometimes', 'nullable', 'string', 'max:255'],
            'endereco.cidade' => ['required_with:endereco', 'string', 'max:255'],
            'endereco.estado' => ['required_with:endereco', 'string', 'size:2', 'alpha:ascii'],
            'endereco.pais' => ['required_with:endereco', 'string', Rule::in(['BR'])],
            'endereco.cep' => ['required_with:endereco', 'string', 'regex:/^\d{5}-?\d{3}$/'],
        ];
    }
}
