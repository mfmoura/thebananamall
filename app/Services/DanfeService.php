<?php

namespace App\Services;

use App\Models\NotaFiscal;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Storage;
use NFePHP\DA\NFe\Danfe;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DanfeService
{
    public function armazenar(NotaFiscal $nota, string $xml): void
    {
        if (trim($xml) === '' || str_contains(strtoupper($xml), '<!DOCTYPE') || strlen($xml) > 10_000_000) {
            throw new HttpException(502, 'XML fiscal inválido.');
        }
        $documento = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);
        try {
            $carregado = $documento->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
        $xpath = new DOMXPath($documento);
        $xpath->registerNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
        $valor = static fn (string $caminho): string => $xpath->evaluate('string('.$caminho.')');
        if (! $carregado
            || $valor('/n:nfeProc/n:NFe/n:infNFe/n:ide/n:tpAmb') !== '2'
            || $valor('/n:nfeProc/n:protNFe/n:infProt/n:tpAmb') !== '2'
            || $valor('/n:nfeProc/n:NFe/n:infNFe/@Id') !== 'NFe'.$nota->chave_acesso
            || (int) $valor('/n:nfeProc/n:NFe/n:infNFe/n:ide/n:nNF') !== (int) $nota->numero
            || (int) $valor('/n:nfeProc/n:NFe/n:infNFe/n:ide/n:serie') !== (int) $nota->serie
            || $valor('/n:nfeProc/n:protNFe/n:infProt/n:chNFe') !== $nota->chave_acesso
            || $valor('/n:nfeProc/n:protNFe/n:infProt/n:nProt') !== $nota->protocolo_autorizacao
            || ! in_array($valor('/n:nfeProc/n:protNFe/n:infProt/n:cStat'), ['100', '150'], true)
            || $valor('/n:nfeProc/n:NFe/n:infNFe/n:emit/n:CNPJ') !== $nota->emitente_documento
        ) {
            throw new HttpException(502, 'O XML não corresponde à NF-e autorizada em homologação.');
        }

        $caminho = 'fiscal/homologacao/'.$nota->id;
        $disk = Storage::disk('local');
        if (! $disk->put($caminho.'/nfe.xml', $xml)) {
            throw new HttpException(503, 'Não foi possível armazenar o XML fiscal.');
        }
        $totais = [];
        foreach (['total_produtos' => 'vProd', 'total_frete' => 'vFrete', 'total_desconto' => 'vDesc', 'valor_total' => 'vNF'] as $campo => $tag) {
            $total = $valor('/n:nfeProc/n:NFe/n:infNFe/n:total/n:ICMSTot/n:'.$tag);
            if (! preg_match('/^\d{1,10}\.\d{2}$/', $total)) {
                throw new HttpException(502, 'Os totais do XML fiscal são inválidos.');
            }
            $totais[$campo] = $total;
        }
        $nota->forceFill(['caminho_xml' => $caminho.'/nfe.xml', ...$totais])->save();
        $pdf = (new Danfe($xml))->render();
        if (! $disk->put($caminho.'/danfe.pdf', $pdf)) {
            throw new HttpException(503, 'Não foi possível armazenar o DANFE.');
        }
        $nota->forceFill(['caminho_danfe' => $caminho.'/danfe.pdf'])->save();
    }
}
