<?php

namespace App\Http\Requests\Api;

class DeleteCategoriaRequest extends AdministradorRequest
{
    public function authorize(): bool
    {
        return $this->user()?->administrador?->acessaPagina('estoque') === true;
    }

    public function rules(): array
    {
        return [];
    }
}
