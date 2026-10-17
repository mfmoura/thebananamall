<?php

namespace App\Http\Requests\Api\Concerns;

trait RegrasProdutoFiscal
{
    protected function regrasProdutoFiscal(): array
    {
        return [
            'configuracao_fiscal' => ['sometimes', 'nullable', 'array:ncm,cfop_interno,cfop_interestadual,unidade,tributos'],
            'configuracao_fiscal.ncm' => ['required_with:configuracao_fiscal', 'string', 'regex:/^\d{8}$/'],
            'configuracao_fiscal.cfop_interno' => ['required_with:configuracao_fiscal', 'string', 'regex:/^5\d{3}$/'],
            'configuracao_fiscal.cfop_interestadual' => ['required_with:configuracao_fiscal', 'string', 'regex:/^6\d{3}$/'],
            'configuracao_fiscal.unidade' => ['required_with:configuracao_fiscal', 'string', 'max:6'],
            'configuracao_fiscal.tributos' => ['required_with:configuracao_fiscal', 'array:icms,pis,cofins,ipi'],
            'configuracao_fiscal.tributos.icms' => ['required_with:configuracao_fiscal', 'array:code,origin,aliquot'],
            'configuracao_fiscal.tributos.icms.code' => ['required_with:configuracao_fiscal', 'string', 'regex:/^\d{2,3}$/'],
            'configuracao_fiscal.tributos.icms.origin' => ['required_with:configuracao_fiscal', 'integer', 'between:0,8'],
            'configuracao_fiscal.tributos.icms.aliquot' => ['sometimes', 'numeric', 'between:0,100'],
            ...$this->regrasImpostoProduto('pis'),
            ...$this->regrasImpostoProduto('cofins'),
            ...$this->regrasImpostoProduto('ipi'),
        ];
    }

    private function regrasImpostoProduto(string $tributo): array
    {
        return [
            "configuracao_fiscal.tributos.$tributo" => ['sometimes', 'array:code,aliquot'],
            "configuracao_fiscal.tributos.$tributo.code" => ["required_with:configuracao_fiscal.tributos.$tributo", 'string', 'regex:/^\d{2}$/'],
            "configuracao_fiscal.tributos.$tributo.aliquot" => ['sometimes', 'numeric', 'between:0,100'],
        ];
    }
}
