<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\RegrasProdutoFiscal;
use Illuminate\Validation\Rule;

class StoreProdutoRequest extends AdministradorRequest
{
    use RegrasProdutoFiscal;

    public function rules(): array
    {
        return [
            ...$this->regrasProdutoFiscal(),
            'codigo' => ['required', 'string', 'max:80', 'unique:produtos,codigo'],
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'categoria' => ['required', 'string', Rule::exists('categorias', 'nome_codigo')->whereNull('deleted_at')],
            'preco' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
