<?php

namespace App\Http\Requests\Api\Concerns;

use Illuminate\Validation\Rule;

trait RegrasDestinatarioFiscal
{
    protected function regrasDestinatarioFiscal(string $prefixo): array
    {
        return [
            $prefixo => ['required', 'array'],
            "$prefixo.nome" => ['required', 'string', 'max:60'],
            "$prefixo.documento" => ['required', 'string', 'regex:/^(\d{11}|\d{14})$/'],
            "$prefixo.indicador_ie" => ['required', 'integer', Rule::in([1, 2, 9])],
            "$prefixo.inscricao_estadual" => ["required_if:$prefixo.indicador_ie,1", 'nullable', 'string', 'max:20'],
            "$prefixo.email" => ['sometimes', 'email'],
            "$prefixo.endereco" => ['required', 'array'],
            "$prefixo.endereco.logradouro" => ['required', 'string', 'max:60'],
            "$prefixo.endereco.numero" => ['required', 'string', 'max:60'],
            "$prefixo.endereco.bairro" => ['required', 'string', 'max:60'],
            "$prefixo.endereco.cidade" => ['required', 'string', 'max:60'],
            "$prefixo.endereco.codigo_municipio" => ['required', 'string', 'regex:/^\d{7}$/'],
            "$prefixo.endereco.estado" => ['required', Rule::in(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'])],
            "$prefixo.endereco.cep" => ['required', 'string', 'regex:/^\d{8}$/'],
        ];
    }
}
