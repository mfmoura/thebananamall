<?php

namespace App\Http\Requests\Api;

class DeleteUsuarioAdminRequest extends MutateUsuarioAdminRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && ! $this->user()->is($this->route('usuario'))
            && (! $this->route('usuario')->administrador()->exists() || $this->user()->administrador->acessaPagina('usuarios'))
            && ! array_diff($this->route('usuario')->administrador?->paginas ?? [], $this->user()->administrador->paginas ?? []);
    }
}
