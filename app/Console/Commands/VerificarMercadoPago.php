<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerificarMercadoPago extends Command
{
    protected $signature = 'mercadopago:verificar';

    protected $description = 'Verifica a configuração local do Mercado Pago sem exibir credenciais ou criar cobranças';

    public function handle(): int
    {
        $pendente = false;
        foreach ([
            'access_token' => 'MERCADO_PAGO_ACCESS_TOKEN',
            'public_key' => 'MERCADO_PAGO_PUBLIC_KEY',
            'webhook_secret' => 'MERCADO_PAGO_WEBHOOK_SECRET',
        ] as $chave => $variavel) {
            $valor = config('mercadopago.'.$chave);
            $presente = is_string($valor) && trim($valor) !== '';
            $this->line($variavel.': '.($presente ? 'configurado' : 'ausente'));
            $pendente = $pendente || ! $presente;
        }

        $origem = rtrim((string) config('app.url'), '/');
        $host = parse_url($origem, PHP_URL_HOST);
        $publica = filter_var($origem, FILTER_VALIDATE_URL)
            && parse_url($origem, PHP_URL_SCHEME) === 'https'
            && is_string($host)
            && ! in_array(strtolower($host), ['localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]'], true)
            && ! str_ends_with(strtolower($host), '.localhost');
        if ($publica) {
            // Não imprime URL completa: ela pode conter credenciais, query ou fragmento.
            $porta = parse_url($origem, PHP_URL_PORT);
            $this->line('Webhook: https://'.$host.($porta ? ':'.$porta : '').'/api/webhooks/mercado-pago');
        } else {
            $this->warn('Configure no painel uma URL pública HTTPS para /api/webhooks/mercado-pago (pode ser o túnel local).');
        }

        if ($pendente) {
            $this->error('Preencha as variáveis ausentes no .env e execute php artisan config:clear.');

            return self::FAILURE;
        }

        $this->info('Configuração local preenchida. Credenciais, entrega do webhook e meios habilitados ainda precisam de homologação na conta.');

        return self::SUCCESS;
    }
}
