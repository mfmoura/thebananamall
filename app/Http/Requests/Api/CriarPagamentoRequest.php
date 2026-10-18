<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class CriarPagamentoRequest extends ShowPedidoRequest
{
    public function rules(): array
    {
        return [
            'meio' => ['required', Rule::in(['pix', 'boleto', 'credit_card', 'debit_card'])],
            'payment_method_id' => ['required_if:meio,credit_card,debit_card', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'token' => ['required_if:meio,credit_card,debit_card', 'string', 'max:512'],
            'parcelas' => ['required_if:meio,credit_card,debit_card', 'integer', 'in:1'],
            'card_number' => ['prohibited'],
            'security_code' => ['prohibited'],
            'cvv' => ['prohibited'],
            'transaction_amount' => ['prohibited'],
        ];
    }
}
