<?php

namespace Tests\Unit;

use App\Jobs\ProcessarPedidoPagamento;
use App\Models\PedidoPagamento;
use App\Services\MercadoPagoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProcessarPedidoPagamentoTest extends TestCase
{
    public static function estados(): array
    {
        return ['aguarda conciliacao' => ['aguardando', true], 'confirmado ou recusado' => ['concluido', false], 'exige verificacao' => ['verificacao_necessaria', false]];
    }

    #[DataProvider('estados')]
    public function test_job_reagenda_somente_pagamentos_que_aguardam_conciliacao(string $estado, bool $reagendar): void
    {
        $pagamento = new PedidoPagamento(['processamento' => $estado]);
        $service = $this->createMock(MercadoPagoService::class);
        $service->expects($this->once())->method('processar')->with(42)->willReturn($pagamento);
        $job = (new ProcessarPedidoPagamento(42))->withFakeQueueInteractions();
        $job->handle($service);
        if ($reagendar) $job->assertReleased(30);
        else $job->assertNotReleased();
    }

    public function test_falha_do_provedor_e_propagada_para_politica_de_retentativa(): void
    {
        $service = $this->createMock(MercadoPagoService::class);
        $service->expects($this->once())->method('processar')->with(42)->willThrowException(new \RuntimeException('provedor indisponível'));
        $this->expectException(\RuntimeException::class);
        (new ProcessarPedidoPagamento(42))->withFakeQueueInteractions()->handle($service);
    }
}
