<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('fiscal:retomar', function (): void {
    $total = app(\App\Services\PedidoFiscalService::class)->retomarPendentes();
    $this->info("Processamentos fiscais elegíveis: {$total}");
})->purpose('Recuperar processamento fiscal após indisponibilidade da fila');

Schedule::command('fiscal:retomar')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
