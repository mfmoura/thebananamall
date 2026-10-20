<?php

namespace Tests\Feature\Api;

use App\Enums\StatusPedido;
use App\Jobs\ProcessarPedidoPagamento;
use App\Services\MercadoPagoService;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\PedidoPagamento;
use App\Models\Produto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MercadoPagoTest extends TestCase
{
    use DatabaseMigrations;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['mercadopago.access_token' => 'test-secret', 'mercadopago.webhook_secret' => 'webhook-secret']);
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $cliente = $usuario->cliente()->create();
        Passport::actingAs($usuario, ['orders:write']);
        $forma = FormaPagamento::create(['codigo' => 'pix', 'nome' => 'Pix', 'ativo' => true]);
        $produto = Produto::create(['codigo' => 'MP-TEST', 'nome' => 'Teste', 'categoria' => 'bandeira', 'ativo' => true]);
        $preco = $produto->precos()->create(['preco' => 25, 'data' => now()]);
        $this->pedido = Pedido::create(['cliente_id' => $cliente->id, 'forma_pagamento_id' => $forma->id,
            'status' => StatusPedido::AguardandoPagamento, 'data' => now(), 'idempotency_key' => fake()->uuid()]);
        $this->pedido->itens()->create(['codigo' => $produto->id, 'preco_id' => $preco->id,
            'preco' => 25, 'quantidade' => 1, 'data' => now()]);
    }

    public function test_pix_confirma_consultando_provedor_e_reutiliza_cobranca(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD123'], 201),
            'api.mercadopago.com/v1/orders/ORD123' => Http::response($this->order()),
        ]);
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(202)->assertJsonPath('data.processamento', 'pendente')
            ->assertJsonMissing(['idempotency_key', 'requisicao_hash', 'test-secret']);
        Http::assertNothingSent();
        Queue::assertPushed(ProcessarPedidoPagamento::class, fn ($job) => $job->queue === 'pagamentos' && $job->connection === 'redis');
        $this->getJson($url)->assertOk()->assertJsonPath('data.order_id', null);
        Http::assertNothingSent();
        app(MercadoPagoService::class)->processar(PedidoPagamento::firstOrFail()->id);
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(202);
        $this->getJson($url)->assertOk();
        $this->assertSame(1, PedidoPagamento::count());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['total_amount'] === '25.00'
            && $r['external_reference'] === (string) $this->pedido->id && $r->hasHeader('X-Idempotency-Key'));
    }

    public function test_cobranca_inclui_frete_no_total(): void
    {
        $this->pedido->update(['custo_frete' => '25.80']);
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/pagamento', ['meio' => 'pix'])->assertStatus(202);
        $dados = PedidoPagamento::firstOrFail()->dados_envio;
        $this->assertSame('50.80', $dados['total_amount']);
        $this->assertSame('50.80', $dados['transactions']['payments'][0]['amount']);
    }

    public function test_timeout_reutiliza_chave_e_nao_libera_cancelamento(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(202);
        $key = PedidoPagamento::firstOrFail()->idempotency_key;
        for ($i = 0; $i < 2; $i++) {
            try { app(MercadoPagoService::class)->processar(PedidoPagamento::firstOrFail()->id); $this->fail('Esperava falha de conexão.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        }
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(202);
        $this->assertSame($key, PedidoPagamento::firstOrFail()->idempotency_key);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Idempotency-Key', $key));
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/cancelar')->assertStatus(409);
    }

    public function test_cliente_nao_pode_pagar_pedido_de_outro(): void
    {
        $outro = Usuario::factory()->create(['email_validado' => true]);
        $outro->cliente()->create();
        Passport::actingAs($outro, ['orders:write']);
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/pagamento', ['meio' => 'pix'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_pendente_nao_confirma_e_instrucoes_pix_ficam_cifradas(): void
    {
        $this->pagamento();
        Http::fake(['*' => Http::response(array_replace($this->order(), [
            'status' => 'action_required', 'status_detail' => 'waiting_transfer', 'total_paid_amount' => '0.00',
            'transactions' => ['payments' => [['payment_method' => ['qr_code' => 'PIX-COPIA-COLA', 'token' => 'NAO-EXPOR']]]],
        ]))]);
        app(MercadoPagoService::class)->processar(PedidoPagamento::firstOrFail()->id);
        $this->getJson('/api/pedidos/'.$this->pedido->id.'/pagamento')->assertOk()
            ->assertJsonPath('data.instrucoes.qr_code', 'PIX-COPIA-COLA')->assertJsonMissingPath('data.instrucoes.token');
        $this->assertStringNotContainsString('PIX-COPIA-COLA', PedidoPagamento::first()->getRawOriginal('instrucoes'));
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
    }

    public function test_falta_de_configuracao_e_meio_incompativel_nao_criam_cobranca(): void
    {
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        config(['mercadopago.access_token' => null]);
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(503);
        config(['mercadopago.access_token' => 'test-secret']);
        $this->postJson($url, ['meio' => 'credit_card', 'payment_method_id' => 'master', 'token' => 'card-token', 'parcelas' => 1])->assertStatus(422);
        $this->assertSame(0, PedidoPagamento::count());
        Http::assertNothingSent();
    }

    public function test_assinatura_invalida_nao_consulta_provedor(): void
    {
        $this->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_assinatura_usa_id_minusculo_no_manifest_e_preserva_id_na_consulta(): void
    {
        $this->pagamento();
        Http::fake(['*' => Http::response($this->order())]);
        $assinatura = hash_hmac('sha256', 'id:ord123;request-id:req123;ts:1742505638683;', 'webhook-secret');
        $this->withHeaders(['x-request-id' => ' req123 ', 'x-signature' => ' TS = 1742505638683 , V1 = '.$assinatura.' '])
            ->postJson('/api/webhooks/mercado-pago?data.id=%20ORD123%20', ['type' => 'order'])->assertOk();
        Http::assertNothingSent();
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        (new \App\Jobs\ProcessarMercadoPagoEvento(\App\Models\MercadoPagoEvento::sole()->id))->handle(app(MercadoPagoService::class));
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.mercadopago.com/v1/orders/ORD123');
    }

    public function test_assinatura_do_simulador_com_id_original_e_aceita_sem_consulta(): void
    {
        $assinatura = hash_hmac('sha256', 'id:ORD123;request-id:req123;ts:1742505638683;', 'webhook-secret');
        $this->withHeaders(['x-request-id' => 'req123', 'x-signature' => 'ts=1742505638683,v1='.$assinatura])
            ->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order'])->assertOk();
        $this->assertSame('ORD123', \App\Models\MercadoPagoEvento::sole()->order_id);
        Http::assertNothingSent();
    }

    public function test_evento_persiste_com_fila_indisponivel_e_scheduler_retoma(): void
    {
        \Illuminate\Support\Facades\Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Fila indisponível'));
        $assinatura = hash_hmac('sha256', 'id:ord123;request-id:req123;ts:1742505638683;', 'webhook-secret');
        $this->withHeaders(['x-request-id' => 'req123', 'x-signature' => 'ts=1742505638683,v1='.$assinatura])
            ->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order'])->assertOk();
        Http::assertNothingSent();
        $evento = \App\Models\MercadoPagoEvento::sole();
        $this->assertSame('pendente', $evento->status);
        $this->assertDatabaseMissing('mercado_pago_eventos', ['chave' => $assinatura]);
        $this->travel(3)->minutes();
        \Illuminate\Support\Facades\Bus::fake();
        $this->assertSame(1, app(\App\Services\MercadoPagoWebhookService::class)->retomarPendentes());
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\ProcessarMercadoPagoEvento::class, fn ($job) => $job->eventoId === $evento->id);
    }

    public function test_falha_do_provedor_preserva_evento_para_retentativa(): void
    {
        $this->pagamento();
        $evento = \App\Models\MercadoPagoEvento::create(['chave' => str_repeat('a', 64), 'order_id' => 'ORD123', 'request_id' => 'req123']);
        Http::fake(['*' => Http::sequence()->pushResponse(Http::failedConnection())->push($this->order())]);
        $job = new \App\Jobs\ProcessarMercadoPagoEvento($evento->id);
        try {
            $job->handle(app(MercadoPagoService::class));
            $this->fail('Esperava falha na consulta.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        $this->assertSame('pendente', $evento->fresh()->status);
        $job->handle(app(MercadoPagoService::class));
        $this->assertSame('concluido', $evento->fresh()->status);
        $this->assertNotNull($evento->fresh()->processado_em);
    }

    public function test_assinatura_malformada_ou_hash_errado_retorna_401_sem_consulta(): void
    {
        foreach (['ts=abc,v1=falso', 'ts=1742505638683,v1=', 'ts=1742505638683,v1='.str_repeat('0', 64)] as $assinatura) {
            $this->withHeaders(['x-request-id' => 'req123', 'x-signature' => $assinatura])
                ->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order'])->assertUnauthorized();
        }
        Http::assertNothingSent();
    }

    public function test_webhook_exige_id_e_request_id_mesmo_quando_sdk_aceitaria_omissao(): void
    {
        $ts = '1742505638683';
        $semRequestId = hash_hmac('sha256', "id:ord123;ts:$ts;", 'webhook-secret');
        $this->withHeaders(['x-signature' => "ts=$ts,v1=$semRequestId"])
            ->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order'])->assertUnauthorized();
        $semId = hash_hmac('sha256', "request-id:req123;ts:$ts;", 'webhook-secret');
        $this->withHeaders(['x-request-id' => 'req123', 'x-signature' => "ts=$ts,v1=$semId"])
            ->postJson('/api/webhooks/mercado-pago', ['type' => 'order'])->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_webhook_consulta_recurso_assinado_ignora_corpo_e_e_idempotente(): void
    {
        $this->pagamento();
        Http::fake(['*' => Http::response($this->order())]);
        $assinatura = hash_hmac('sha256', 'id:ord123;request-id:req123;ts:1742505638683;', 'webhook-secret');
        for ($i = 0; $i < 2; $i++) {
            $this->withHeaders(['x-request-id' => 'req123', 'x-signature' => 'ts=1742505638683,v1='.$assinatura])
                ->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order', 'data' => ['id' => 'FORGED']])->assertOk();
        }
        Http::assertNothingSent();
        $evento = \App\Models\MercadoPagoEvento::sole();
        Queue::assertPushed(\App\Jobs\ProcessarMercadoPagoEvento::class, fn ($job) => $job->eventoId === $evento->id && $job->queue === 'pagamentos');
        $job = new \App\Jobs\ProcessarMercadoPagoEvento($evento->id);
        $job->handle(app(MercadoPagoService::class));
        $job->handle(app(MercadoPagoService::class));
        $this->assertSame('concluido', $evento->fresh()->status);
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.mercadopago.com/v1/orders/ORD123');
    }

    public function test_valor_moeda_e_referencia_incorretos_nao_confirmam_pagamento(): void
    {
        $this->pagamento();
        foreach ([['total_amount' => '24.99'], ['currency_id' => 'USD'], ['external_reference' => '000'.$this->pedido->id]] as $alteracao) {
            Http::fake(['*' => Http::response(array_replace($this->order(), $alteracao))]);
            $this->assertSincronizacaoIncompativel();
            $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        }
    }

    public function test_configuracao_do_checkout_expoe_so_public_key_e_nao_permite_cache(): void
    {
        config(['mercadopago.public_key' => 'chave-publica-de-teste']);
        $this->getJson('/api/pagamentos/configuracao')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.public_key', 'chave-publica-de-teste')->assertJsonPath('data.parcelas', 1)
            ->assertDontSee('test-secret')->assertDontSee('webhook-secret');
        config(['mercadopago.public_key' => null]);
        $this->getJson('/api/pagamentos/configuracao')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_cartao_tokenizado_usa_total_do_pedido_e_uma_parcela_sem_persistir_token(): void
    {
        $forma = FormaPagamento::create(['codigo' => 'cartao_credito', 'nome' => 'Cartão', 'ativo' => true]);
        $this->pedido->update(['forma_pagamento_id' => $forma->id]);
        $this->pedido->processamentoFiscal()->create(['chave' => fake()->uuid(), 'dados_cliente' => ['documento' => '12345678909'], 'etapa' => 'aguardando_pagamento']);
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD123'], 201),
            'api.mercadopago.com/v1/orders/ORD123' => Http::response($this->order()),
        ]);
        $dados = ['meio' => 'credit_card', 'payment_method_id' => 'visa', 'token' => 'token-temporario-cartao', 'parcelas' => 1];
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/pagamento', $dados)->assertStatus(202)
            ->assertDontSee('token-temporario-cartao')->assertJsonMissingPath('data.dados_envio');
        $pagamento = PedidoPagamento::firstOrFail();
        $this->assertStringNotContainsString('token-temporario-cartao', $pagamento->getRawOriginal('dados_envio'));
        $this->assertSame('token-temporario-cartao', data_get($pagamento->dados_envio, 'transactions.payments.0.payment_method.token'));
        $this->assertStringNotContainsString('token-temporario-cartao', serialize(new ProcessarPedidoPagamento($pagamento->id)));
        Http::assertNothingSent();
        app(MercadoPagoService::class)->processar($pagamento->id);
        $this->assertNull($pagamento->fresh()->dados_envio);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['total_amount'] === '25.00'
            && $r['payer']['identification'] === ['type' => 'CPF', 'number' => '12345678909']
            && $r['transactions']['payments'][0]['payment_method'] === ['id' => 'visa', 'type' => 'credit_card', 'token' => 'token-temporario-cartao', 'installments' => 1]);
        $this->assertStringNotContainsString('token-temporario-cartao', PedidoPagamento::first()->getRawOriginal('instrucoes') ?? '');
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
    }

    public function test_rejeita_dados_brutos_do_cartao_e_parcelamento(): void
    {
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'pix', 'card_number' => '4111111111111111', 'cvv' => '123'])->assertUnprocessable();
        $this->postJson($url, ['meio' => 'credit_card', 'payment_method_id' => 'visa', 'token' => 'token', 'parcelas' => 2])->assertUnprocessable();
        $this->postJson($url, ['meio' => 'pix', 'transaction_amount' => '0.01'])->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertDatabaseCount('pedido_pagamentos', 0);
    }

    public function test_preserva_order_id_quando_consulta_apos_criacao_falha(): void
    {
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD123'], 201),
            'api.mercadopago.com/v1/orders/ORD123' => Http::sequence()->pushFailedConnection()->push($this->order()),
        ]);
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(202);
        try { app(MercadoPagoService::class)->processar(PedidoPagamento::firstOrFail()->id); $this->fail('Esperava falha de conexão.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        $this->assertDatabaseHas('pedido_pagamentos', ['pedido_id' => $this->pedido->id, 'order_id' => 'ORD123']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.order_id', 'ORD123');
        app(MercadoPagoService::class)->processar(PedidoPagamento::firstOrFail()->id);
        Http::assertSent(fn ($r) => $r->method() === 'GET');
        $this->assertCount(1, Http::recorded(fn ($r) => $r->method() === 'POST'));
    }

    public function test_bff_checkout_configuracao_e_pix_preservam_autorizacao(): void
    {
        config(['bff.client_secret' => str_repeat('s', 64), 'mercadopago.public_key' => 'chave-publica-de-teste']);
        $this->seed(\Database\Seeders\BffClientSeeder::class);
        $usuario = $this->pedido->cliente->usuario;
        $this->postJson('/oauth/login', ['login' => $usuario->login, 'password' => 'password'])->assertOk();
        $url = $this->postJson('/bff/authorize')->assertOk()->json('authorization_url');
        $callback = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->get($callback)->assertRedirect('/oauth/callback');
        $this->getJson('/bff/api/pagamentos/configuracao')->assertOk()->assertJsonPath('data.public_key', 'chave-publica-de-teste')->assertDontSee('test-secret');
        $this->get('/checkout/pagamento/'.$this->pedido->id)->assertOk();
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD123'], 201),
            'api.mercadopago.com/v1/orders/ORD123' => Http::response($this->order()),
        ]);
        $this->postJson('/bff/api/pedidos/'.$this->pedido->id.'/pagamento', ['meio' => 'pix'])->assertStatus(202);
        $this->getJson('/bff/api/pedidos/'.$this->pedido->id.'/pagamento')->assertOk();
    }

    public function test_order_brasileira_sem_currency_id_e_conflitos_de_pais_moeda(): void
    {
        $this->pagamento();
        $order = $this->order();
        unset($order['currency_id']);
        Http::fake(['*' => Http::sequence()
            ->push([...$order, 'country_code' => 'ARG', 'currency' => 'BRL'])
            ->push([...$order, 'country_code' => 'BRA', 'currency' => 'USD'])
            ->push($order)
            ->push([...$order, 'country_code' => 'BRA'])]);
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        for ($i = 0; $i < 3; $i++) {
            $this->assertSincronizacaoIncompativel();
            $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        }
        app(MercadoPagoService::class)->sincronizar('ORD123');
        $this->getJson($url)->assertOk();
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
    }

    public function test_scheduler_retoma_registro_duravel_e_respeita_verificacao_manual(): void
    {
        $this->pagamento();
        $pagamento = PedidoPagamento::firstOrFail();
        $pagamento->timestamps = false;
        $pagamento->forceFill(['updated_at' => now()->subMinutes(3)])->save();
        $this->assertSame(1, app(MercadoPagoService::class)->retomarPendentes());
        Queue::assertPushed(ProcessarPedidoPagamento::class, fn ($job) => $job->pagamentoId === $pagamento->id);
        $pagamento->update(['processamento' => 'verificacao_necessaria']);
        $this->assertSame(0, app(MercadoPagoService::class)->retomarPendentes());
        Http::assertNothingSent();
    }

    public function test_job_consulta_pendente_sem_recriar_order_e_finaliza_apos_confirmacao(): void
    {
        $this->pagamento();
        Http::fake(['*' => Http::sequence()
            ->push(array_replace($this->order(), ['status' => 'action_required', 'total_paid_amount' => '0.00']))
            ->push($this->order())]);
        $job = (new ProcessarPedidoPagamento(PedidoPagamento::firstOrFail()->id))->withFakeQueueInteractions();
        $job->handle(app(MercadoPagoService::class));
        $job->assertReleased(30);
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        $job = (new ProcessarPedidoPagamento(PedidoPagamento::firstOrFail()->id))->withFakeQueueInteractions();
        $job->handle(app(MercadoPagoService::class));
        $job->assertNotReleased();
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        $this->getJson('/api/pedidos/'.$this->pedido->id)->assertOk()
            ->assertJsonPath('data.pagamento.processamento', 'concluido')->assertJsonMissingPath('data.pagamento.dados_envio');
        Http::assertSentCount(2);
    }

    public function test_esgotamento_de_tentativas_nao_declara_rejeicao_nem_permite_nova_cobranca(): void
    {
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(202);
        $pagamento = PedidoPagamento::firstOrFail();
        (new ProcessarPedidoPagamento($pagamento->id))->failed(new \RuntimeException('erro técnico'));
        $this->getJson($url)->assertOk()->assertJsonPath('data.processamento', 'verificacao_necessaria')
            ->assertDontSee('erro técnico');
        $this->assertNull($pagamento->fresh()->dados_envio);
        app(MercadoPagoService::class)->processar($pagamento->id);
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        Http::assertNothingSent();
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/cancelar')->assertStatus(409);
    }

    public function test_solicitacao_antiga_sem_order_nao_reenvia_token_ou_cria_cobranca(): void
    {
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/pagamento', ['meio' => 'pix'])->assertStatus(202);
        $pagamento = PedidoPagamento::firstOrFail();
        $pagamento->created_at = now()->subMinutes(11);
        $pagamento->save();
        $resultado = app(MercadoPagoService::class)->processar($pagamento->id);
        $this->assertSame('verificacao_necessaria', $resultado->processamento);
        $this->assertNull($resultado->dados_envio);
        Http::assertNothingSent();
        // Uma resposta tardia assinada ainda pode recuperar a mesma Order.
        Http::fake(['*' => Http::response($this->order())]);
        app(MercadoPagoService::class)->sincronizar('ORD123');
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
    }

    public function test_indisponibilidade_da_publicacao_nao_perde_solicitacao_aceita(): void
    {
        \Illuminate\Support\Facades\Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Redis indisponível'));
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/pagamento', ['meio' => 'pix'])->assertStatus(202)
            ->assertJsonPath('data.processamento', 'pendente')->assertDontSee('Redis indisponível');
        $pagamento = PedidoPagamento::firstOrFail();
        $this->assertSame((string) $this->pedido->id, $pagamento->dados_envio['external_reference']);
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        Http::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modalidadesEmail')]
    public function test_confirmacao_enfileira_email_uma_vez_por_pedido(string $codigo): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->pedido->formaPagamento->update(['codigo' => $codigo]);
        $this->pagamento();
        Http::fake(['*' => Http::response($this->order())]);
        $service = app(MercadoPagoService::class);
        $service->sincronizar('ORD123');
        $service->sincronizar('ORD123');
        $usuario = $this->pedido->cliente->usuario;
        \Illuminate\Support\Facades\Notification::assertSentToTimes($usuario, \App\Notifications\PagamentoConfirmadoNotification::class, 1);
        \Illuminate\Support\Facades\Notification::assertSentTo($usuario, \App\Notifications\PagamentoConfirmadoNotification::class,
            fn ($notification) => $notification->pedidoId === $this->pedido->id
                && $notification->connection === 'redis' && $notification->queue === 'emails'
                && $notification->afterCommit === true
                && $notification instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted);
    }

    public static function modalidadesEmail(): array
    {
        return array_map(fn ($codigo) => [$codigo], ['pix', 'boleto', 'cartao_credito', 'cartao_debito']);
    }

    public function test_pendencia_e_recusa_nao_enviam_email_de_confirmacao(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->pagamento();
        foreach (['action_required', 'failed'] as $status) {
            Http::fake(['*' => Http::response(array_replace($this->order(), ['status' => $status, 'total_paid_amount' => '0.00']))]);
            app(MercadoPagoService::class)->sincronizar('ORD123');
        }
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_email_de_confirmacao_descarta_conta_excluida_e_inclui_link_do_pedido(): void
    {
        $usuario = $this->pedido->cliente->usuario;
        $this->pedido->update(['status' => StatusPedido::Pago]);
        $notification = new \App\Notifications\PagamentoConfirmadoNotification($this->pedido->id);
        $this->assertTrue($notification->shouldSend($usuario, 'mail'));
        $mail = $notification->toMail($usuario);
        $this->assertSame(url('/pedidos/'.$this->pedido->id), $mail->actionUrl);
        $this->assertStringContainsString('Pagamento confirmado', $mail->subject);
        $usuario->delete();
        $this->assertFalse($notification->shouldSend($usuario, 'mail'));
    }

    private function pagamento(): void
    {
        PedidoPagamento::create(['pedido_id' => $this->pedido->id, 'idempotency_key' => fake()->uuid(),
            'requisicao_hash' => str_repeat('a', 64), 'order_id' => 'ORD123']);
    }

    private function assertSincronizacaoIncompativel(): void
    {
        try {
            app(MercadoPagoService::class)->sincronizar('ORD123');
            $this->fail('A cobrança incompatível deve ser rejeitada.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    private function order(): array
    {
        return ['id' => 'ORD123', 'external_reference' => (string) $this->pedido->id, 'currency_id' => 'BRL',
            'total_amount' => '25.00', 'total_paid_amount' => '25.00', 'status' => 'processed', 'status_detail' => 'accredited'];
    }
}
