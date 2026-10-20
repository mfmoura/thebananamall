<?php

namespace App\Services;

use App\Jobs\ProcessarMercadoPagoEvento;
use App\Models\MercadoPagoEvento;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookService
{
    public function receber(string $orderId, string $requestId, string $signature): void
    {
        // Somente identificadores já autenticados; corpo e assinatura não são persistidos.
        $evento = MercadoPagoEvento::withTrashed()->firstOrCreate([
            'chave' => hash('sha256', json_encode([$orderId, $requestId, $signature], JSON_THROW_ON_ERROR)),
        ], ['order_id' => $orderId, 'request_id' => $requestId]);
        if (! $evento->trashed() && $evento->status === 'pendente') {
            $this->enfileirar($evento);
        }
    }

    public function retomarPendentes(): int
    {
        $total = 0;
        MercadoPagoEvento::where('status', 'pendente')->where('updated_at', '<=', now()->subMinutes(2))
            ->eachById(function (MercadoPagoEvento $evento) use (&$total): void {
                $this->enfileirar($evento);
                $total++;
            });

        return $total;
    }

    private function enfileirar(MercadoPagoEvento $evento): void
    {
        try {
            ProcessarMercadoPagoEvento::dispatch($evento->id)->afterCommit();
        } catch (\Throwable) {
            Log::warning('Publicação de evento Mercado Pago pendente.', ['evento_id' => $evento->id]);
        }
    }
}
