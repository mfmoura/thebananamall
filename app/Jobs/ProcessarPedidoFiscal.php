<?php

namespace App\Jobs;

use App\Models\PedidoProcessamentoFiscal;
use App\Services\PedidoFiscalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessarPedidoFiscal implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 40;

    public int $timeout = 180;

    public int $uniqueFor = 14400;

    public function __construct(public readonly int $processamentoId)
    {
        $this->onConnection('redis')->onQueue('fiscal');
    }

    public function uniqueId(): string
    {
        return (string) $this->processamentoId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('pedido-fiscal:'.$this->processamentoId))->releaseAfter(15)->expireAfter(240)];
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function tags(): array
    {
        return ['fiscal', 'processamento:'.$this->processamentoId];
    }

    public function handle(PedidoFiscalService $service): void
    {
        $atraso = $service->processar($this->processamentoId);
        if ($atraso !== null) {
            $this->release($atraso);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        PedidoProcessamentoFiscal::query()->whereKey($this->processamentoId)->whereNotIn('etapa', ['concluido', 'cancelado'])
            ->update(['etapa' => 'falhou', 'ultimo_erro' => 'As tentativas automáticas foram esgotadas. Verifique o Horizon e retome o job original.']);
    }
}
