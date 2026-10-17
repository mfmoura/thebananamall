<?php

namespace App\Http\Requests\Api;

use App\Enums\NaturezaOperacaoNotaFiscal;
use App\Http\Requests\Api\Concerns\RegrasDestinatarioFiscal;
use Illuminate\Validation\Rule;

class EmitirNotaFiscalRequest extends StoreNotaFiscalRequest
{
    use RegrasDestinatarioFiscal;

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos,id,deleted_at,NULL'],
            'natureza_operacao' => ['required', Rule::enum(NaturezaOperacaoNotaFiscal::class)],
            'numero' => ['required', 'integer', 'min:1', 'max:999999999'],
            'serie' => ['required', 'integer', 'min:1', 'max:889'],
            'consumidor_final' => ['required', 'boolean'],
            'finalidade' => ['required', 'integer', Rule::in([1, 2, 3, 4])],
            'chaves_referenciadas' => ['required_if:finalidade,2,4', 'array'],
            'chaves_referenciadas.*' => ['string', 'regex:/^\d{44}$/'],
            ...$this->regrasDestinatarioFiscal('destinatario'),
            'pagamentos' => ['required', 'array', 'min:1'],
            'pagamentos.*' => ['array:meio,valor'],
            'pagamentos.*.meio' => ['required', 'string', 'regex:/^\d{2}$/', Rule::notIn(['99'])],
            'pagamentos.*.valor' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'itens' => ['required', 'array', 'min:1', 'max:990'],
            'itens.*' => ['array:produto_id,ncm,cfop,unidade_comercial,quantidade,valor_unitario,tributos'],
            'itens.*.produto_id' => ['required', 'integer', 'exists:produtos,id,deleted_at,NULL'],
            'itens.*.ncm' => ['required', 'string', 'regex:/^\d{8}$/'],
            'itens.*.cfop' => ['required', 'string', 'regex:/^\d{4}$/'],
            'itens.*.unidade_comercial' => ['required', 'string', 'max:6'],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'itens.*.valor_unitario' => ['required', 'numeric', 'gte:0', 'decimal:0,2'],
            'itens.*.tributos' => ['required', 'array:icms,pis,cofins,ipi'],
            'itens.*.tributos.icms' => ['required', 'array:code,origin,aliquot'],
            'itens.*.tributos.icms.code' => ['required', 'string', 'regex:/^\d{2,3}$/'],
            'itens.*.tributos.icms.origin' => ['required', 'integer', 'between:0,8'],
            'itens.*.tributos.icms.aliquot' => ['sometimes', 'numeric', 'between:0,100'],
            ...$this->regrasTributo('pis'),
            ...$this->regrasTributo('cofins'),
            ...$this->regrasTributo('ipi'),
        ];
    }

    private function regrasTributo(string $tributo): array
    {
        return [
            "itens.*.tributos.$tributo" => ['sometimes', 'array:code,aliquot'],
            "itens.*.tributos.$tributo.code" => ['required_with:itens.*.tributos.'.$tributo, 'string', 'regex:/^\d{2}$/'],
            "itens.*.tributos.$tributo.aliquot" => ['sometimes', 'numeric', 'between:0,100'],
        ];
    }
}
