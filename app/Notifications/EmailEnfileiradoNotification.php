<?php

namespace App\Notifications;

use Carbon\CarbonInterval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Redis;

abstract class EmailEnfileiradoNotification extends Notification implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public $tries = 3;
    // Inclui renderização das views e SMTP (limitado separadamente a 20s).
    public $timeout = 90;

    public function __construct()
    {
        $this->locale('pt_BR');
        $this->onConnection('redis')->onQueue('emails')->afterCommit();
    }

    protected function duracaoLegivel(int $minutos): string
    {
        return CarbonInterval::minutes($minutos)->cascade()->locale('pt_BR')->forHumans(['join' => true]);
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    protected function tokenAindaValido(string $url, string $prefixo): bool
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $parametros);
        $token = $parametros['token'] ?? null;

        return is_string($token) && $token !== '' && (bool) Redis::exists($prefixo.hash('sha256', $token));
    }
}
