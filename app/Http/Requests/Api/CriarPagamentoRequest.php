<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class CriarPagamentoRequest extends ShowPedidoRequest
{
    public function rules(): array
    {
        return [
            'meio' => ['required', Rule::in(['pix', 'credit_card', 'debit_card'])],
            'payment_method_id' => ['required_unless:meio,pix', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'token' => ['required_unless:meio,pix', 'string', 'max:512'],
            'parcelas' => ['required_unless:meio,pix', 'integer', 'min:1', 'max:12'],
        ];
    }
}
