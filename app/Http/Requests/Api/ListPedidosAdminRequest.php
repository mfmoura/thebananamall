<?php

namespace App\Http\Requests\Api;

use App\Enums\StatusPedido;
use Illuminate\Validation\Rule;

class ListPedidosAdminRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'pedido_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', Rule::enum(StatusPedido::class)],
        ];
    }
}
