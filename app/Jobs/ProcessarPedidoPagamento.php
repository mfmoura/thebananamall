<?php

namespace App\Jobs;

use App\Models\PedidoPagamento;
use App\Services\MercadoPagoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

class ProcessarPedidoPagamento implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 8;
    public int $timeout = 45;
    public int $uniqueFor = 120;

    public function __construct(public readonly int $pagamentoId)
    {
        $this->onConnection('redis')->onQueue('pagamentos');
    }

    public function uniqueId(): string
    {
        return (string) $this->pagamentoId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('pedido-pagamento:'.$this->pagamentoId))->releaseAfter(15)->expireAfter(60)];
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120];
    }

    public function handle(MercadoPagoService $service): void
    {
        $pagamento = $service->processar($this->pagamentoId);
        if ($pagamento->processamento === 'aguardando') {
            $this->release(30);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        DB::transaction(function (): void {
            $pagamento = PedidoPagamento::query()->lockForUpdate()->find($this->pagamentoId);
            if (! $pagamento || $pagamento->processamento === 'concluido') return;
            // Resultado incerto não significa rejeição. Com ID remoto, continuar conciliando.
            $pagamento->update(['processamento' => $pagamento->order_id ? 'aguardando' : 'verificacao_necessaria', 'dados_envio' => null]);
        });
    }
}
