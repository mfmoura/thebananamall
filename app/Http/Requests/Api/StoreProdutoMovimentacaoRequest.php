<?php

namespace App\Http\Requests\Api;

use App\Enums\DirecaoMovimentacao;
use Illuminate\Validation\Rule;

class StoreProdutoMovimentacaoRequest extends AdministradorRequest
{
    public function authorize(): bool
    {
        $administrador = $this->user()?->administrador;

        return $this->input('tipo', 'ajuste') === 'nota'
            ? ($administrador?->acessaPagina('contabilidade') && $this->input('direcao') === 'entrada')
            : ($administrador?->acessaPagina('estoque') || $administrador?->acessaPagina('manutencao'));
    }

    public function rules(): array
    {
        return [
            'tamanho' => ['sometimes', 'nullable', 'string', 'max:30'],
            'produto_id' => ['required', 'integer', 'exists:produtos,id,deleted_at,NULL'],
            'tipo' => ['sometimes', Rule::in(['ajuste', 'nota'])],
            'direcao' => ['required', Rule::enum(DirecaoMovimentacao::class)],
            'quantidade' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'nota' => [Rule::requiredIf($this->input('tipo') === 'nota'), 'nullable', 'string', 'max:120'],
            'observacao' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
