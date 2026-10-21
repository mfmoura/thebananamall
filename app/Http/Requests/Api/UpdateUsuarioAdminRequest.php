<?php

namespace App\Http\Requests\Api;

use App\Enums\NivelAcesso;
use App\Http\Requests\Api\Concerns\ValidaPaginasUsuarioAdmin;
use Illuminate\Validation\Rule;

class UpdateUsuarioAdminRequest extends MutateUsuarioAdminRequest
{
    use ValidaPaginasUsuarioAdmin;

    public function rules(): array
    {
        $usuario = $this->route('usuario');
        return [
            'login' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'login')->withoutTrashed()->ignore($usuario->id)],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:15', 'confirmed'],
            'email' => ['prohibited'], 'tipo' => ['prohibited'], 'email_validado' => ['prohibited'],
            'acesso_loja' => ['sometimes', Rule::prohibitedIf(! $usuario->administrador()->exists()), 'required', 'boolean'],
            'paginas' => ['sometimes', Rule::prohibitedIf(! $usuario->administrador()->exists()), 'required', 'array', 'min:1'],
            'paginas.*' => ['required', 'distinct', Rule::enum(NivelAcesso::class)],
            'nome' => ['sometimes', Rule::prohibitedIf(! $usuario->logistica()->exists()), 'required', 'string', 'max:255'],
            'webhook' => ['sometimes', Rule::prohibitedIf(! $usuario->logistica()->exists()), 'required', 'url', 'max:2048'],
        ];
    }
}
