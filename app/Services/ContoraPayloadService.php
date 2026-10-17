<?php

namespace App\Services;

use App\Models\NotaFiscal;

class ContoraPayloadService
{
    public function montar(NotaFiscal $nota, array $dados): array
    {
        $destinatario = $dados['destinatario'];
        $endereco = $destinatario['endereco'];
        $itens = $nota->itens()->orderBy('id')->get()->values()->map(
            static fn ($item, int $indice): array => [
                'code' => $item->codigo_produto,
                'name' => $item->descricao,
                'ncm' => $item->ncm,
                'cfop' => $item->cfop,
                'unit' => $item->unidade_comercial,
                'quantity' => (float) $item->quantidade,
                'unit_price' => (float) $item->valor_unitario,
                'taxes' => $dados['itens'][$indice]['tributos'],
            ],
        )->all();

        return [
            'document_type' => 'nfe',
            'environment' => 'homologacao',
            'series' => (int) $nota->serie,
            'number' => (int) $nota->numero,
            'payload' => [
                'nature_operation' => ucfirst($nota->natureza_operacao->value),
                'operation_type' => $nota->tipo_operacao->value,
                'consumer_final' => (bool) $dados['consumidor_final'],
                'presence_indicator' => 2,
                'purpose' => (int) $dados['finalidade'],
                'referenced_documents' => array_map(static fn (string $chave): array => ['access_key' => $chave], $dados['chaves_referenciadas'] ?? []),
                'recipient' => array_filter([
                    'name' => $destinatario['nome'],
                    'document' => $destinatario['documento'],
                    'state_registration_indicator' => (int) $destinatario['indicador_ie'],
                    'state_registration' => $destinatario['inscricao_estadual'] ?? null,
                    'email' => $destinatario['email'] ?? null,
                    'address' => [
                        'street' => $endereco['logradouro'], 'number' => $endereco['numero'],
                        'district' => $endereco['bairro'], 'city_name' => $endereco['cidade'],
                        'city_code' => $endereco['codigo_municipio'], 'state_code' => $endereco['estado'],
                        'postal_code' => $endereco['cep'],
                    ],
                ], static fn ($valor): bool => $valor !== null),
                'items' => $itens,
                'payments' => array_map(static fn (array $pagamento): array => [
                    'method' => $pagamento['meio'], 'amount' => (float) $pagamento['valor'],
                ], $dados['pagamentos']),
            ],
        ];
    }
}
