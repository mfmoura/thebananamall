<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class UpdateEmailUsuarioAdminRequest extends MutateUsuarioAdminRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')
            ->withoutTrashed()->where('email_validado', true)->ignore($this->route('usuario')->id)]];
    }
}
