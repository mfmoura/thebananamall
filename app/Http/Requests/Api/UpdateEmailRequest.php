<?php

namespace App\Http\Requests\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmailRequest extends FormRequest
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
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($usuario->id),
            ],
        ];
    }
}
