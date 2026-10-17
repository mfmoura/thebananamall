<?php

namespace App\Http\Requests\Api;

use App\Enums\NivelAcesso;
use App\Models\Administrador;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAdministradorRequest extends AdministradorRequest
{
    public function rules(): array
    {
        /** @var Administrador $administrador */
        $administrador = $this->route('administrador');

        return [
            'login' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'login')->ignore($administrador->usuario_id)],
            'password' => ['sometimes', 'required', 'string', 'min:15', 'max:64', 'confirmed'],
            'email' => ['prohibited'],
            'paginas' => ['sometimes', 'required', 'array', 'min:1'],
            'paginas.*' => ['required', 'distinct', Rule::enum(NivelAcesso::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $paginas = $this->input('paginas');

            if ($paginas === null) {
                return;
            }

            $paginasPermitidas = $this->user()?->administrador?->paginas ?? [];

            if (is_array($paginas) && count(array_diff($paginas, $paginasPermitidas)) > 0) {
                $validator->errors()->add('paginas', 'Você não pode conceder páginas que não possui.');
            }
        }];
    }
}
