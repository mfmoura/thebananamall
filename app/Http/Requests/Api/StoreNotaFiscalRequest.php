<?php

namespace App\Http\Requests\Api;

use App\Enums\NaturezaOperacaoNotaFiscal;
use Illuminate\Validation\Rule;

class StoreNotaFiscalRequest extends AdministradorRequest
{
    public function authorize(): bool
    {
        return $this->user()?->administrador?->acessaPagina('contabilidade') === true;
    }

    public function rules(): array
    {
        $provedor = $this->input('provedor') ?: 'manual';

        return [
            'natureza_operacao' => ['required', Rule::enum(NaturezaOperacaoNotaFiscal::class)],
            'pedido_id' => ['sometimes', 'nullable', 'integer', 'exists:pedidos,id,deleted_at,NULL'],
            'provedor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'referencia_provedor' => [
                'required', 'string', 'max:255',
                Rule::unique('notas_fiscais', 'referencia_provedor')
                    ->where(fn ($query) => $query->where('provedor', $provedor)),
            ],
            'chave_acesso' => ['sometimes', 'nullable', 'string', 'max:60', 'unique:notas_fiscais,chave_acesso'],
            'numero' => ['required', 'string', 'max:255'],
            'serie' => ['sometimes', 'nullable', 'string', 'max:255'],
            'data_emissao' => ['required', 'date'],
            'data_autorizacao' => ['sometimes', 'nullable', 'date'],
            'caminho_xml' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'caminho_danfe' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'total_frete' => ['sometimes', 'numeric', 'gte:0', 'decimal:0,2'],
            'total_desconto' => ['sometimes', 'numeric', 'gte:0', 'decimal:0,2'],
            'total_impostos' => ['sometimes', 'numeric', 'gte:0', 'decimal:0,2'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['required', 'integer', 'exists:produtos,id,deleted_at,NULL'],
            'itens.*.codigo_produto' => ['sometimes', 'string', 'max:80'],
            'itens.*.descricao' => ['sometimes', 'string', 'max:255'],
            'itens.*.ncm' => ['sometimes', 'nullable', 'string', 'size:8'],
            'itens.*.cfop' => ['sometimes', 'nullable', 'string', 'size:4'],
            'itens.*.unidade_comercial' => ['sometimes', 'nullable', 'string', 'max:10'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'itens.*.valor_unitario' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
            'itens.*.valor_total' => ['sometimes', 'numeric', 'gte:0', 'decimal:0,2'],
            'itens.*.cst_csosn' => ['sometimes', 'nullable', 'string', 'max:4'],
            'itens.*.base_icms' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'decimal:0,2'],
            'itens.*.aliquota_icms' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'decimal:0,4'],
            'itens.*.valor_icms' => ['sometimes', 'nullable', 'numeric', 'gte:0', 'decimal:0,2'],
        ];
    }
}
