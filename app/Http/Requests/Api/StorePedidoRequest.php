<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StorePedidoRequest extends ClientePedidoRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'forma_pagamento_id' => [
                'required',
                'integer',
                Rule::exists('formas_pagamento', 'id')->where('ativo', true),
            ],
            'idempotency_key' => ['required', 'uuid', 'max:36'],
        ];
    }
}
