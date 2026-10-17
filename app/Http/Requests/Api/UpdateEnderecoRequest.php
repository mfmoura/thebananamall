<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class UpdateEnderecoRequest extends ShowEnderecoRequest
{
    public function rules(): array
    {
        return [
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:30'],
            'complemento' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'estado' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'pais' => ['required', 'string', Rule::in(['BR'])],
            'cep' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
        ];
    }
}
