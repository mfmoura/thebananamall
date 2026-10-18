<?php

namespace App\Http\Requests\Api;

class ListProdutosAdminRequest extends ListProdutosRequest
{
    public function authorize(): bool
    {
        return $this->user()?->administrador()->exists() === true;
    }
}
