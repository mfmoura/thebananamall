<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEnderecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuarioId = $this->input('usuario_id');

        return $usuarioId === null
            || $this->user()?->administrador()->exists() === true
            || (int) $usuarioId === $this->user()?->id;
    }

    public function rules(): array
    {
        return [
            'usuario_id' => ['sometimes', 'required', 'integer', 'exists:users,id,deleted_at,NULL'],
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
