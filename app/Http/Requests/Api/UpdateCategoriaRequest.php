<?php

namespace App\Http\Requests\Api;

class UpdateCategoriaRequest extends AdministradorRequest
{
    public function authorize(): bool
    {
        return $this->user()?->administrador?->acessaPagina('estoque') === true;
    }

    public function rules(): array
    {
        return [
            'nome_codigo' => ['prohibited'],
            'nome' => ['required', 'string', 'max:255'],
        ];
    }
}
