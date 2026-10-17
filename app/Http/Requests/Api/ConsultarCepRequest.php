<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarCepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['cep' => $this->route('cep')]);
    }

    public function rules(): array
    {
        return ['cep' => ['required', 'string', 'regex:/^\d{8}$/']];
    }
}
