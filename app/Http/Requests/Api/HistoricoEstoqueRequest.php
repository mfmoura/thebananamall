<?php

namespace App\Http\Requests\Api;

class HistoricoEstoqueRequest extends AdministradorRequest
{
    public function rules(): array
    {
        return [
            'produto_id' => ['required', 'integer', 'exists:produtos,id,deleted_at,NULL'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }
}
