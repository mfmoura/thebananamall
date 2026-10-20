<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('pagamentos:retomar', function (): void {
    $total = app(\App\Services\MercadoPagoService::class)->retomarPendentes();
    $this->info("Pagamentos elegíveis: {$total}");
})->purpose('Recuperar envio e conciliação de pagamentos após indisponibilidade da fila');
Schedule::command('pagamentos:retomar')->everyMinute()->withoutOverlapping();

Artisan::command('webhooks:retomar', function (): void {
    $total = app(\App\Services\MercadoPagoWebhookService::class)->retomarPendentes();
    $this->info("Eventos elegíveis: {$total}");
})->purpose('Recuperar eventos autenticados após indisponibilidade da fila');
Schedule::command('webhooks:retomar')->everyMinute()->withoutOverlapping();

Artisan::command('fiscal:retomar', function (): void {
    $total = app(\App\Services\PedidoFiscalService::class)->retomarPendentes();
    $this->info("Processamentos fiscais elegíveis: {$total}");
})->purpose('Recuperar processamento fiscal após indisponibilidade da fila');

Schedule::command('fiscal:retomar')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('usuarios:limpar-expirados', function (): void {
    $total = app(\App\Services\UsuarioExclusaoService::class)->limparExpirados();
    $this->info("Contas excluídas logicamente: {$total}");
})->purpose('Excluir contas não confirmadas, expiradas e sem login');
Schedule::command('usuarios:limpar-expirados')->everyMinute()->withoutOverlapping();

Artisan::command('carrinhos:sanitizar', function (): void {
    $total = app(\App\Services\CarrinhoService::class)->sanitizarReservas();
    $this->info("Reservas vencidas ou órfãs liberadas: {$total}");
})->purpose('Aplicar prazos dos carrinhos e liberar reservas vencidas ou órfãs');
Schedule::command('carrinhos:sanitizar')->everyMinute()->withoutOverlapping();
if (app()->environment('local') && class_exists(\Laravel\Telescope\Telescope::class)) {
    Schedule::command('telescope:prune --hours=24')->daily();

    Artisan::command('desempenho:resumo', function (): void {
        $entradas = \Illuminate\Support\Facades\DB::table('telescope_entries')->where('type', 'request')->latest('sequence')->limit(10)->get();
        $linhas = $entradas->map(function ($entrada): array {
            $dados = json_decode($entrada->content, true);
            $consultas = \Illuminate\Support\Facades\DB::table('telescope_entries')->where('batch_id', $entrada->batch_id)->where('type', 'query')->pluck('content');
            $sql = $consultas->sum(fn ($conteudo) => (float) str_replace(',', '', json_decode($conteudo, true)['time'] ?? '0'));

            return [$dados['uri'] ?? '', $dados['response_status'] ?? '', $dados['duration'] ?? '', $consultas->count(), round($sql, 2)];
        });
        $this->table(['Rota', 'HTTP', 'Duração ms', 'Consultas', 'SQL ms'], $linhas);
    })->purpose('Resumir tempos recentes registrados pelo Telescope local');
}
