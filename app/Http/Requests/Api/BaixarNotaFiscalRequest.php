<?php

namespace App\Http\Requests\Api;

class BaixarNotaFiscalRequest extends ConsultarNotaFiscalRequest
{
    public function authorize(): bool
    {
        return parent::authorize() || $this->user()?->logistica()->exists() === true;
    }
}
