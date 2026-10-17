<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoPagoConfiguracaoTest extends TestCase
{
    public function test_reporta_campos_ausentes_sem_chamar_provedor(): void
    {
        Http::preventStrayRequests();
        config(['mercadopago.access_token' => '', 'mercadopago.public_key' => null,
            'mercadopago.webhook_secret' => ' ', 'app.url' => 'http://localhost']);

        $this->artisan('mercadopago:verificar')
            ->expectsOutput('MERCADO_PAGO_ACCESS_TOKEN: ausente')
            ->expectsOutput('MERCADO_PAGO_PUBLIC_KEY: ausente')
            ->expectsOutput('MERCADO_PAGO_WEBHOOK_SECRET: ausente')
            ->assertFailed();
        Http::assertNothingSent();
    }

    public function test_configuracao_preenchida_nao_expoe_segredos(): void
    {
        Http::preventStrayRequests();
        config(['mercadopago.access_token' => 'segredo-token', 'mercadopago.public_key' => 'chave-publica',
            'mercadopago.webhook_secret' => 'segredo-webhook', 'app.url' => 'https://usuario:senha@loja.example?segredo=valor']);

        $this->artisan('mercadopago:verificar')
            ->expectsOutput('MERCADO_PAGO_ACCESS_TOKEN: configurado')
            ->expectsOutput('MERCADO_PAGO_PUBLIC_KEY: configurado')
            ->expectsOutput('MERCADO_PAGO_WEBHOOK_SECRET: configurado')
            ->expectsOutput('Webhook: https://loja.example/api/webhooks/mercado-pago')
            ->doesntExpectOutputToContain('segredo-token')
            ->doesntExpectOutputToContain('segredo-webhook')
            ->doesntExpectOutputToContain('senha')
            ->assertSuccessful();
        Http::assertNothingSent();
    }
}
