<?php

namespace App\Http\Requests\Api;

use App\Enums\NivelAcesso;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAdministradorRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('users', 'login')->withoutTrashed()],
            'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->withoutTrashed()->where('email_validado', true)],
            'password' => ['required', 'string', 'min:8', 'max:15', 'confirmed'],
            'paginas' => ['required', 'array', 'min:1'],
            'paginas.*' => ['required', 'distinct', Rule::enum(NivelAcesso::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $paginas = $this->input('paginas', []);
            $paginasPermitidas = $this->user()?->administrador?->paginas ?? [];

            if (is_array($paginas) && count(array_diff($paginas, $paginasPermitidas)) > 0) {
                $validator->errors()->add('paginas', 'Você não pode conceder páginas que não possui.');
            }
        }];
    }
}
