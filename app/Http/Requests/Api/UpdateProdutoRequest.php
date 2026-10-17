<?php

namespace App\Http\Requests\Api;

use App\Enums\CategoriaProduto;
use App\Http\Requests\Api\Concerns\RegrasProdutoFiscal;
use App\Models\Produto;
use Illuminate\Validation\Rule;

class UpdateProdutoRequest extends AdministradorRequest
{
    use RegrasProdutoFiscal;

    public function rules(): array
    {
        /** @var Produto $produto */
        $produto = $this->route('produto');

        return [
            ...$this->regrasProdutoFiscal(),
            'codigo' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('produtos', 'codigo')->ignore($produto)],
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'categoria' => ['sometimes', 'required', Rule::enum(CategoriaProduto::class)],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
