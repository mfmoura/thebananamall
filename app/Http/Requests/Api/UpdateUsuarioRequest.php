<?php

namespace App\Http\Requests\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof Usuario && $this->user()?->is($usuario) === true;
    }

    public function rules(): array
    {
        /** @var Usuario $usuario */
        $usuario = $this->route('usuario');

        return [
            'email' => ['prohibited'],
            'login' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('users', 'login')->withoutTrashed()->ignore($usuario->id),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:15', 'confirmed'],
        ];
    }
}
