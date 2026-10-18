<?php

namespace App\Http\Requests\Api;

use App\Models\Usuario;

class MutateUsuarioAdminRequest extends AdministradorRequest
{
    public function authorize(): bool
    {
        $usuario = $this->route('usuario');
        return parent::authorize() && $usuario instanceof Usuario && ! $usuario->visitante()->exists()
            && ! array_diff($usuario->administrador?->paginas ?? [], $this->user()->administrador->paginas ?? []);
    }
}
