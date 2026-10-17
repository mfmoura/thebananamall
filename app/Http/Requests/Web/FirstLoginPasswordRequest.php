<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class FirstLoginPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:15', 'max:64', 'confirmed', 'different:current_password'],
        ];
    }
}
