<?php

namespace App\Http\Requests\Api;

use App\Models\Pedido;

class ShowPedidoRequest extends ClientePedidoRequest
{
    public function authorize(): bool
    {
        return parent::authorize()
            && $this->route('pedido') instanceof Pedido
            && $this->route('pedido')->cliente_id === $this->cliente()->id;
    }
}
