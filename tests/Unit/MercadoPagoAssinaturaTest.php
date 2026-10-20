<?php

namespace Tests\Unit;

use App\Services\MercadoPagoAssinaturaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MercadoPagoAssinaturaTest extends TestCase
{
    // Vetores calculados independentemente com HMACSHA256 do .NET.
    private const HASH = 'bc01b37fc7fd74cf94ce6973805ae984fe35d62f03f9d0ddf8d6f0b17553754c';

    #[DataProvider('vetores')]
    public function test_manifest_com_campos_presentes_e_ausentes(?string $id, ?string $requestId, string $hash): void
    {
        $this->assertTrue((new MercadoPagoAssinaturaService)->validar('ts=1742505638683,v1='.$hash, $requestId, $id, 'webhook-secret'));
    }

    public static function vetores(): array
    {
        return [
            ['ORD123', 'req123', self::HASH],
            ['ORD123', 'req123', '14aefd2b6c81b6ab757542a23c6a5da27d253870f75485de19bccd8fe07dcb2e'],
            [null, 'req123', '6d65f6d7bbe839227ed746491261becee745c6e068dde808201eb9957b81dbca'],
            ['ORD123', null, '5cad0dd40761bbcc1f736d1ff668a6b6a27b1460991fe5add52f4b416f5f995a'],
            [null, null, '64feb3f7d29e88d4f6a050177150c58b603fe95729d816032e6536755c7eba09'],
        ];
    }

    public function test_ordem_e_espacos_do_header_nao_alteram_manifest(): void
    {
        $this->assertTrue((new MercadoPagoAssinaturaService)->validar(' V1 = '.strtoupper(self::HASH).' , TS = 1742505638683 ', ' req123 ', ' OrD123 ', 'webhook-secret'));
    }

    public function test_todos_os_campos_e_segredo_estao_vinculados_a_assinatura(): void
    {
        $validador = new MercadoPagoAssinaturaService;
        $header = 'ts=1742505638683,v1='.self::HASH;
        $this->assertFalse($validador->validar($header, 'outro', 'ORD123', 'webhook-secret'));
        $this->assertFalse($validador->validar($header, 'req123', 'ORD124', 'webhook-secret'));
        $this->assertFalse($validador->validar(str_replace('1742505638683', '1742505638', $header), 'req123', 'ORD123', 'webhook-secret'));
        $this->assertFalse($validador->validar($header, 'req123', 'ORD123', 'outro'));
        $this->assertFalse($validador->validar($header, 'req123', 'ORD123', ''));
    }

    #[DataProvider('headersInvalidos')]
    public function test_rejeita_headers_invalidos_ou_ambiguos(?string $header): void
    {
        $this->assertFalse((new MercadoPagoAssinaturaService)->validar($header, 'req123', 'ORD123', 'webhook-secret'));
    }

    public static function headersInvalidos(): array
    {
        return array_map(fn ($header) => [$header], [
            null, '', 'v1='.self::HASH, 'ts=1742505638683',
            'ts=abc,v1='.self::HASH, 'ts=1742505638683,v1='.str_repeat('g', 64),
            'ts=1742505638683,v1=1234', 'ts=1742505638683,v1=',
            'ts=1742505638683,ts=1742505638683,v1='.self::HASH,
            'ts=1742505638683,v1='.self::HASH.',v1='.self::HASH,
            'ts=1742505638683,v1='.self::HASH.',invalido',
            'ts=1742505638683,v1='.str_repeat('0', 64),
        ]);
    }
}
