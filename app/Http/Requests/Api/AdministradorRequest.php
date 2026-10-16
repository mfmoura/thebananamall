<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdministradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->administrador()->exists() === true;
    }
}
