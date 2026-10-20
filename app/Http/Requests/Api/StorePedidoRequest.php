<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\RegrasDestinatarioFiscal;
use Illuminate\Validation\Rule;

class StorePedidoRequest extends ClientePedidoRequest
{
    use RegrasDestinatarioFiscal;

    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return [
            'frete_cotacao_id' => ['required', 'uuid'],
            'frete_servico' => ['required', 'string', Rule::in(array_map('strval', array_keys(config('frete.servicos'))))],
            'custo_frete' => ['prohibited'],
            'frete' => ['prohibited'],
            ...$this->regrasDestinatarioFiscal('dados_fiscais'),
            'forma_pagamento_id' => [
                'required',
                'integer',
                Rule::exists('formas_pagamento', 'id')->where('ativo', true)->whereNull('deleted_at'),
            ],
            'idempotency_key' => ['required', 'uuid', 'max:36'],
        ];
    }
}
