<?php

namespace App\Services;

class MercadoPagoAssinaturaService
{
    public function validar(?string $signature, ?string $requestId, ?string $dataId, string $segredo): bool
    {
        if ($segredo === '' || $signature === null || trim($signature) === '') return false;

        $campos = [];
        foreach (explode(',', $signature) as $parte) {
            $par = explode('=', trim($parte), 2);
            if (count($par) !== 2) return false;
            [$nome, $valor] = array_map('trim', $par);
            $nome = strtolower($nome);
            if (! in_array($nome, ['ts', 'v1'], true)) continue;
            // Não escolher arbitrariamente entre timestamps ou assinaturas duplicados.
            if (array_key_exists($nome, $campos)) return false;
            $campos[$nome] = $valor;
        }

        $ts = $campos['ts'] ?? '';
        $assinatura = $campos['v1'] ?? '';
        if (! preg_match('/^[0-9]+$/D', $ts) || ! preg_match('/^[a-fA-F0-9]{64}$/D', $assinatura)) return false;

        $dataId = trim($dataId ?? '');
        $requestId = trim($requestId ?? '');
        $sufixo = $requestId !== '' ? 'request-id:'.$requestId.';' : '';
        // Usar exatamente ts do header, sem conversão de unidade ou substituição pelo relógio local.
        $sufixo .= 'ts:'.$ts.';';
        $manifest = ($dataId !== '' ? 'id:'.strtolower($dataId).';' : '').$sufixo;
        $recebida = strtolower($assinatura);
        if (hash_equals(hash_hmac('sha256', $manifest, $segredo), $recebida)) return true;

        // Capturas do simulador da aplicação principal assinam o ID original.
        // A compatibilidade exige o mesmo HMAC, segredo, request-id e timestamp.
        if ($dataId === strtolower($dataId)) return false;
        $manifestOriginal = 'id:'.$dataId.';'.$sufixo;

        return hash_equals(hash_hmac('sha256', $manifestOriginal, $segredo), $recebida);
    }
}
