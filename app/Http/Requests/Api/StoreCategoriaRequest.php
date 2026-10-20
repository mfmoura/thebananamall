<?php

namespace App\Http\Requests\Api;

class StoreCategoriaRequest extends AdministradorRequest
{
    public function authorize(): bool
    {
        return $this->user()?->administrador?->acessaPagina('estoque') === true;
    }

    public function rules(): array
    {
        return [
            'nome_codigo' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/', 'unique:categorias,nome_codigo'],
            'nome' => ['required', 'string', 'max:255'],
        ];
    }
}
