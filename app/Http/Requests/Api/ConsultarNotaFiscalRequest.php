<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConsultarNotaFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->administrador?->acessaPagina('contabilidade') === true;
    }

    public function rules(): array
    {
        return [];
    }
}
