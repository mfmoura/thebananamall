<?php

namespace App\Http\Requests\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;

class ShowUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario instanceof Usuario && $this->user()?->is($usuario) === true;
    }
}
