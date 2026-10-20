<?php

namespace App\Http\Requests\Api;

use App\Enums\NivelAcesso;
use App\Enums\TipoUsuario;
use App\Http\Requests\Api\Concerns\ValidaPaginasUsuarioAdmin;
use Illuminate\Validation\Rule;

class StoreUsuarioAdminRequest extends AdministradorRequest
{
    use ValidaPaginasUsuarioAdmin;

    public function authorize(): bool
    {
        return parent::authorize() && ($this->input('tipo') === 'administrador'
            ? ($this->user()->administrador->acessaPagina('usuarios') || $this->user()->administrador->acessaPagina('manutencao'))
            : $this->user()->administrador->acessaPagina('manutencao'));
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoUsuario::class)],
            'login' => ['required', 'string', 'max:255', Rule::unique('users', 'login')->withoutTrashed()],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->withoutTrashed()->where('email_validado', true)],
            'password' => ['required', 'string', 'min:8', 'max:15', 'confirmed'],
            'paginas' => ['required_if:tipo,administrador', 'prohibited_unless:tipo,administrador', 'array', 'min:1'],
            'paginas.*' => ['required', 'distinct', Rule::enum(NivelAcesso::class)],
            'nome' => ['required_if:tipo,logistica', 'prohibited_unless:tipo,logistica', 'string', 'max:255'],
            'webhook' => ['required_if:tipo,logistica', 'prohibited_unless:tipo,logistica', 'url', 'max:2048'],
            'email_validado' => ['prohibited'],
            'perfil' => ['prohibited'],
        ];
    }
}
