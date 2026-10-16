<?php

namespace App\Http\Requests\Api;

use App\Models\Cliente;
use Illuminate\Foundation\Http\FormRequest;

class CarrinhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->cliente()->exists() === true;
    }

    public function cliente(): Cliente
    {
        /** @var Cliente $cliente */
        $cliente = $this->user()->cliente()->firstOrFail();

        return $cliente;
    }
}
