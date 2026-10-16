<?php

namespace App\Http\Requests\Api;

use App\Enums\NivelAcesso;
use App\Enums\TipoUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tipo = TipoUsuario::tryFrom($this->input('tipo', TipoUsuario::Cliente->value));

        if ($tipo === null || $tipo === TipoUsuario::Cliente) {
            return true;
        }

        $administrador = $this->user()?->administrador;

        if ($administrador === null) {
            return false;
        }

        if (
            $tipo === TipoUsuario::Administrador
            && $this->input('perfil.nivel_de_acesso') === NivelAcesso::SuperAdministrador->value
        ) {
            return $administrador->nivel_de_acesso === NivelAcesso::SuperAdministrador;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255', 'unique:users,login'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'tipo' => ['sometimes', 'required', Rule::enum(TipoUsuario::class)],
            'perfil' => ['required_if:tipo,administrador,logistica', 'array'],
            'perfil.nivel_de_acesso' => [
                'required_if:tipo,administrador',
                Rule::enum(NivelAcesso::class),
            ],
            'perfil.nome' => ['required_if:tipo,logistica', 'string', 'max:255'],
            'perfil.webhook' => ['required_if:tipo,logistica', 'url', 'max:2048'],
            'endereco' => ['sometimes', 'nullable', 'array'],
            'endereco.logradouro' => ['required_with:endereco', 'string', 'max:255'],
            'endereco.numero' => ['required_with:endereco', 'string', 'max:30'],
            'endereco.complemento' => ['sometimes', 'nullable', 'string', 'max:255'],
            'endereco.cidade' => ['required_with:endereco', 'string', 'max:255'],
            'endereco.estado' => ['required_with:endereco', 'string', 'size:2', 'alpha:ascii'],
            'endereco.pais' => ['required_with:endereco', 'string', 'size:2', 'alpha:ascii'],
            'endereco.cep' => ['required_with:endereco', 'string', 'max:20'],
        ];
    }
}
