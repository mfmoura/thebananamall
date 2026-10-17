<?php

namespace Tests\Feature\Api;

use App\Enums\StatusPedido;
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
        $this->postJson($url, ['meio' => 'pix'])->assertCreated()->assertJsonPath('data.order_id', 'ORD123')
            ->assertJsonMissing(['idempotency_key', 'requisicao_hash', 'test-secret']);
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        $this->postJson($url, ['meio' => 'pix'])->assertCreated();
        $this->getJson($url)->assertOk();
        $this->assertSame(1, PedidoPagamento::count());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['total_amount'] === '25.00'
            && $r['external_reference'] === (string) $this->pedido->id && $r->hasHeader('X-Idempotency-Key'));
    }

    public function test_timeout_reutiliza_chave_e_nao_libera_cancelamento(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $url = '/api/pedidos/'.$this->pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(503);
        $key = PedidoPagamento::firstOrFail()->idempotency_key;
        $this->postJson($url, ['meio' => 'pix'])->assertStatus(503);
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
        $this->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_webhook_consulta_recurso_assinado_ignora_corpo_e_e_idempotente(): void
    {
        $this->pagamento();
        Http::fake(['*' => Http::response($this->order())]);
        $assinatura = hash_hmac('sha256', 'id:ORD123;request-id:req123;ts:1742505638683;', 'webhook-secret');
        for ($i = 0; $i < 2; $i++) {
            $this->withHeaders(['x-request-id' => 'req123', 'x-signature' => 'ts=1742505638683,v1='.$assinatura])
                ->postJson('/api/webhooks/mercado-pago?data.id=ORD123', ['type' => 'order', 'data' => ['id' => 'FORGED']])->assertOk();
        }
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.mercadopago.com/v1/orders/ORD123');
    }

    public function test_valor_moeda_e_referencia_incorretos_nao_confirmam_pagamento(): void
    {
        $this->pagamento();
        foreach ([['total_amount' => '24.99'], ['currency_id' => 'USD'], ['external_reference' => '000'.$this->pedido->id]] as $alteracao) {
            Http::fake(['*' => Http::response(array_replace($this->order(), $alteracao))]);
            $this->getJson('/api/pedidos/'.$this->pedido->id.'/pagamento')->assertStatus(409);
            $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        }
    }

    private function pagamento(): void
    {
        PedidoPagamento::create(['pedido_id' => $this->pedido->id, 'idempotency_key' => fake()->uuid(),
            'requisicao_hash' => str_repeat('a', 64), 'order_id' => 'ORD123']);
    }

    private function order(): array
    {
        return ['id' => 'ORD123', 'external_reference' => (string) $this->pedido->id, 'currency_id' => 'BRL',
            'total_amount' => '25.00', 'total_paid_amount' => '25.00', 'status' => 'processed', 'status_detail' => 'accredited'];
    }
}
