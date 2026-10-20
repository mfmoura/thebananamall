<?php

namespace App\Jobs;

use App\Models\MercadoPagoEvento;
use App\Services\MercadoPagoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessarMercadoPagoEvento implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;
    public int $timeout = 45;
    public int $uniqueFor = 120;

    public function __construct(public readonly int $eventoId)
    {
        $this->onConnection('redis')->onQueue('pagamentos');
    }

    public function uniqueId(): string
    {
        return (string) $this->eventoId;
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('mercado-pago-evento:'.$this->eventoId))->releaseAfter(15)->expireAfter(60)];
    }

    public function handle(MercadoPagoService $service): void
    {
        $evento = MercadoPagoEvento::find($this->eventoId);
        if (! $evento || $evento->status === 'concluido') return;
        $service->sincronizar($evento->order_id);
        $evento->update(['status' => 'concluido', 'processado_em' => now()]);
    }

    public function failed(?\Throwable $exception): void
    {
        MercadoPagoEvento::whereKey($this->eventoId)->where('status', 'pendente')->update(['status' => 'falhou']);
    }
}
