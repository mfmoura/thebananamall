<?php

namespace App\Services;

use App\Models\NotaFiscal;
use Illuminate\Validation\ValidationException;

class NfeIoPayloadService
{
    public function montar(NotaFiscal $nota, array $dados): array
    {
        if (! in_array((int) $dados['finalidade'], [1, 2], true)) {
            throw ValidationException::withMessages(['finalidade' => 'A NFE.io está habilitada para emissão normal ou complementar neste recorte.']);
        }
        $destinatario = $dados['destinatario'];
        $endereco = $destinatario['endereco'];
        $modelos = $nota->itens()->orderBy('id')->get()->values();
        $frete = (int) round((float) $nota->total_frete * 100);
        $restante = $frete;
        $total = (float) $nota->total_produtos;
        $itens = [];
        foreach ($modelos as $indice => $item) {
            $valor = round((float) $item->quantidade * (float) $item->valor_unitario, 2);
            $rateio = $indice === $modelos->count() - 1 ? $restante
                : min($restante, $total > 0 ? (int) round($frete * $valor / $total) : 0);
            $restante -= $rateio;
            $tributos = $dados['itens'][$indice]['tributos'];
            $tax = [];
            foreach ($tributos as $tipo => $tributo) {
                $codigo = $tributo['code'];
                // O cadastro atual não contém os campos de ST, redução ou diferimento.
                $permitidos = match ($tipo) {
                    'icms' => ['00', '40', '41', '102', '103', '300', '400'],
                    'pis', 'cofins' => ['01', '02', '04', '05', '06', '07', '08', '09'],
                    default => [],
                };
                if (! in_array($codigo, $permitidos, true)) {
                    throw ValidationException::withMessages(["itens.$indice.tributos.$tipo" => 'Tributação exige campos ainda não disponíveis para NFE.io.']);
                }
                $tax[$tipo] = [$tipo === 'icms' && strlen($codigo) === 3 ? 'csosn' : 'cst' => $codigo];
                if ($tipo === 'icms') {
                    $tax[$tipo]['origin'] = (string) $tributo['origin'];
                }
                if (($tipo === 'icms' && $codigo === '00') || ($tipo !== 'icms' && in_array($codigo, ['01', '02'], true))) {
                    if (! isset($tributo['aliquot'])) {
                        throw ValidationException::withMessages(["itens.$indice.tributos.$tipo.aliquot" => 'Informe a alíquota fiscal.']);
                    }
                    $base = round($valor + $rateio / 100, 2);
                    $tax[$tipo] += ['baseTax' => $base, 'rate' => (float) $tributo['aliquot'], 'amount' => round($base * $tributo['aliquot'] / 100, 2)];
                    if ($tipo === 'icms') {
                        $tax[$tipo]['baseTaxModality'] = '3';
                    }
                }
            }
            $itens[] = [
                'code' => $item->codigo_produto, 'description' => $item->descricao,
                'ncm' => $item->ncm, 'cfop' => (int) $item->cfop, 'unit' => $item->unidade_comercial,
                'quantity' => (float) $item->quantidade, 'unitAmount' => (float) $item->valor_unitario,
                'totalAmount' => $valor, 'freightAmount' => $rateio / 100,
                'codeGTIN' => 'SEM GTIN', 'codeTaxGTIN' => 'SEM GTIN', 'tax' => $tax,
            ];
        }
        $metodos = ['01' => 'Cash', '02' => 'Cheque', '03' => 'CreditCard', '04' => 'DebitCard', '05' => 'StoreCredict', '10' => 'FoodVouchers', '11' => 'MealVouchers', '12' => 'GiftVouchers', '13' => 'FuelVouchers', '14' => 'MercantileDuplicate', '15' => 'BankBill', '16' => 'BankDeposit', '17' => 'InstantPayment', '18' => 'WireTransfer', '19' => 'Cashback', '20' => 'StaticInstantPayment', '21' => 'StoreCredit', '22' => 'ElectronicPaymentNotInformed', '90' => 'WithoutPayment'];
        $pagamentos = [];
        foreach ($dados['pagamentos'] as $indice => $pagamento) {
            if (! isset($metodos[$pagamento['meio']])) {
                throw ValidationException::withMessages(["pagamentos.$indice.meio" => 'Meio fiscal não suportado pela NFE.io.']);
            }
            $pagamentos[] = [
                'method' => $metodos[$pagamento['meio']], 'amount' => (float) $pagamento['valor'],
                ...(in_array($pagamento['meio'], ['03', '04'], true) ? ['card' => ['integrationPaymentType' => 'Integrated']] : []),
            ];
        }

        return [
            'serie' => (int) $nota->serie, 'number' => (int) $nota->numero,
            'operationNature' => ucfirst($nota->natureza_operacao->value),
            'operationType' => $nota->tipo_operacao->value === 'saida' ? 'Outgoing' : 'Incoming',
            'purposeType' => [1 => 'Normal', 2 => 'Complement', 3 => 'Adjustment', 4 => 'Devolution'][$dados['finalidade']],
            'consumerType' => $dados['consumidor_final'] ? 'FinalConsumer' : 'Normal', 'presenceType' => 'Internet',
            'buyer' => array_filter([
                'name' => $destinatario['nome'], 'federalTaxNumber' => (int) $destinatario['documento'],
                'type' => strlen($destinatario['documento']) === 14 ? 'LegalEntity' : 'NaturalPerson',
                'stateTaxNumberIndicator' => [1 => 'TaxPayer', 2 => 'Exempt', 9 => 'NonTaxPayer'][$destinatario['indicador_ie']],
                'stateTaxNumber' => $destinatario['inscricao_estadual'] ?? null, 'email' => $destinatario['email'] ?? null,
                'address' => [
                    'street' => $endereco['logradouro'], 'number' => $endereco['numero'], 'district' => $endereco['bairro'],
                    'city' => ['code' => $endereco['codigo_municipio'], 'name' => $endereco['cidade']],
                    'state' => $endereco['estado'], 'postalCode' => $endereco['cep'], 'country' => 'BRA',
                ],
            ], static fn ($valor) => $valor !== null),
            'items' => $itens, 'payment' => [['paymentDetail' => $pagamentos]],
            'transport' => ['freightModality' => [0 => 'ByIssuer', 1 => 'ByReceiver', 2 => 'ByThirdParties', 3 => 'OwnBySender', 4 => 'OwnByBuyer', 9 => 'Free'][$dados['modalidade_frete'] ?? 9]],
            'additionalInformation' => ['taxDocumentsReference' => array_map(static fn ($chave) => ['documentElectronicInvoice' => ['accessKey' => $chave]], $dados['chaves_referenciadas'] ?? [])],
        ];
    }
}
