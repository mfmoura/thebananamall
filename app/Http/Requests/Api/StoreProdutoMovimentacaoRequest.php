<?php

namespace App\Http\Requests\Api;

use App\Enums\DirecaoMovimentacao;
use Illuminate\Validation\Rule;

class StoreProdutoMovimentacaoRequest extends AdministradorRequest
{
    public function authorize(): bool
    {
        $administrador = $this->user()?->administrador;

        return $this->input('direcao') === DirecaoMovimentacao::Entrada->value
            && ($administrador?->acessaPagina('estoque') || $administrador?->acessaPagina('manutencao'));
    }

    public function rules(): array
    {
        return [
            'produto_id' => ['required', 'integer', 'exists:produtos,id'],
            'direcao' => ['required', Rule::in([DirecaoMovimentacao::Entrada->value])],
            'quantidade' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'nota' => ['required', 'string', 'max:120'],
            'observacao' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
