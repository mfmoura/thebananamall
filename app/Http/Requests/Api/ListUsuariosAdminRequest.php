<?php

namespace App\Http\Requests\Api;

use App\Enums\TipoUsuario;
use Illuminate\Validation\Rule;

class ListUsuariosAdminRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'busca' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tipo' => ['sometimes', 'nullable', Rule::enum(TipoUsuario::class)],
        ];
    }
}
