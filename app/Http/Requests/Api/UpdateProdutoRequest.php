<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\Api\Concerns\RegrasProdutoFiscal;
use App\Models\Produto;
use Illuminate\Validation\Rule;

class UpdateProdutoRequest extends AdministradorRequest
{
    use RegrasProdutoFiscal;
    use \App\Http\Requests\Api\Concerns\RegrasEmbalagem;

    public function rules(): array
    {
        /** @var Produto $produto */
        $produto = $this->route('produto');

        return [
            ...$this->regrasProdutoFiscal(),
            ...$this->regrasEmbalagem(),
            'grade' => ['sometimes', 'array', 'max:50'],
            'grade.*' => ['required', 'string', 'max:30', 'distinct:ignore_case', 'regex:/^\\S(?:.*\\S)?$/u'],
            'codigo' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('produtos', 'codigo')->ignore($produto)],
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'categoria' => ['sometimes', 'required', 'string', Rule::exists('categorias', 'nome_codigo')->whereNull('deleted_at')],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
