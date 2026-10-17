<?php

namespace App\Observers;

use App\Models\Pedido;
use App\Services\PedidoFiscalService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class PedidoObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(Pedido $pedido): void
    {
        if ($pedido->wasChanged('status')) {
            app(PedidoFiscalService::class)->enfileirar($pedido);
        }
    }
}
