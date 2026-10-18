<?php

namespace Tests\Feature\Api;

use App\Enums\StatusPedido;
use App\Jobs\ProcessarPedidoPagamento;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\PedidoPagamento;
use App\Models\Produto;
use App\Models\Usuario;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PixBoletoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['mercadopago.access_token' => 'credencial-ficticia', 'mercadopago.webhook_secret' => 'segredo-ficticio']);
    }

    private function pedido(string $codigo): Pedido
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $cliente = $usuario->cliente()->create();
        Passport::actingAs($usuario, ['orders:write']);
        $forma = FormaPagamento::create(['codigo' => $codigo, 'nome' => strtoupper($codigo), 'ativo' => true]);
        $produto = Produto::create(['codigo' => 'PIX-BOLETO', 'nome' => 'Produto', 'categoria' => 'bandeira', 'ativo' => true]);
        $preco = $produto->precos()->create(['preco' => '25.00', 'data' => now()]);
        $pedido = Pedido::create(['cliente_id' => $cliente->id, 'forma_pagamento_id' => $forma->id,
            'status' => StatusPedido::AguardandoPagamento, 'data' => now(), 'idempotency_key' => fake()->uuid()]);
        $pedido->itens()->create(['codigo' => $produto->id, 'preco_id' => $preco->id, 'preco' => '25.00', 'quantidade' => 2, 'data' => now()]);

        return $pedido;
    }

    public function test_pix_disponibiliza_qr_code_e_confirma_somente_apos_webhook_validado(): void
    {
        $pedido = $this->pedido('pix');
        $url = '/api/pedidos/'.$pedido->id.'/pagamento';
        $order = ['id' => 'ORDPIXTESTE', 'external_reference' => (string) $pedido->id, 'country_code' => 'BRA',
            'total_amount' => '50.00', 'total_paid_amount' => '0.00', 'status' => 'action_required', 'status_detail' => 'waiting_transfer',
            'transactions' => ['payments' => [['payment_method' => ['id' => 'pix', 'type' => 'bank_transfer',
                'qr_code' => 'PIX-COPIA-COLA-FICTICIO', 'qr_code_base64' => 'aW1hZ2Vt', 'ticket_url' => 'https://example.test/pix']]]]];
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORDPIXTESTE'], 201),
            'api.mercadopago.com/v1/orders/ORDPIXTESTE' => Http::sequence()->push($order)->push([
                ...$order, 'status' => 'processed', 'status_detail' => 'accredited', 'total_paid_amount' => '50.00',
            ]),
        ]);
        $this->postJson($url, ['meio' => 'pix'])->assertAccepted()->assertJsonPath('data.processamento', 'pendente');
        Http::assertNothingSent();
        $pagamento = PedidoPagamento::sole();
        Queue::assertPushed(ProcessarPedidoPagamento::class, fn ($job) => $job->pagamentoId === $pagamento->id);
        (new ProcessarPedidoPagamento($pagamento->id))->withFakeQueueInteractions()->handle(app(MercadoPagoService::class));
        $this->getJson($url)->assertOk()->assertJsonPath('data.processamento', 'aguardando')
            ->assertJsonPath('data.instrucoes.qr_code', 'PIX-COPIA-COLA-FICTICIO')
            ->assertJsonPath('data.instrucoes.qr_code_base64', 'aW1hZ2Vt')
            ->assertJsonPath('data.instrucoes.ticket_url', 'https://example.test/pix');
        $this->assertSame(StatusPedido::AguardandoPagamento, $pedido->fresh()->status);
        $this->assertStringNotContainsString('PIX-COPIA-COLA-FICTICIO', $pagamento->fresh()->getRawOriginal('instrucoes'));
        Http::assertSentCount(2);
        $ts = (string) now()->getTimestampMs();
        $assinatura = hash_hmac('sha256', "id:ORDPIXTESTE;request-id:pix-teste;ts:$ts;", 'segredo-ficticio');
        $this->withHeaders(['x-request-id' => 'pix-teste', 'x-signature' => "ts=$ts,v1=$assinatura"])
            ->postJson('/api/webhooks/mercado-pago?data.id=ORDPIXTESTE', ['type' => 'order'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('data.processamento', 'concluido');
        $this->assertSame(StatusPedido::Pago, $pedido->fresh()->status);
        $this->assertDatabaseCount('pedido_pagamentos', 1);
        Http::assertSentCount(3);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['total_amount'] === '50.00'
            && $r['transactions']['payments'][0]['payment_method'] === ['id' => 'pix', 'type' => 'bank_transfer']);
    }

    public function test_boleto_gera_linha_digitavel_com_snapshot_e_aguarda_compensacao(): void
    {
        $pedido = $this->pedido('boleto');
        $pedido->processamentoFiscal()->create(['chave' => fake()->uuid(), 'etapa' => 'aguardando_pagamento',
            'dados_cliente' => ['nome' => 'Maria da Silva', 'documento' => '12345678909', 'endereco' => [
                'logradouro' => 'Rua do Pedido', 'numero' => '10', 'bairro' => 'Centro', 'cidade' => 'São Paulo', 'estado' => 'SP', 'cep' => '01001000']]]);
        $order = ['id' => 'ORDBOLETO', 'external_reference' => (string) $pedido->id, 'country_code' => 'BRA',
            'total_amount' => '50.00', 'total_paid_amount' => '0.00', 'status' => 'action_required', 'status_detail' => 'waiting_payment',
            'transactions' => ['payments' => [['payment_method' => ['id' => 'boleto', 'type' => 'ticket',
                'digitable_line' => '23793380296060054351030006333303799140000020000', 'barcode_content' => 'CODIGO-FICTICIO',
                'ticket_url' => 'https://example.test/boleto', 'token' => 'NAO-EXPOR']]]]];
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORDBOLETO'], 201),
            'api.mercadopago.com/v1/orders/ORDBOLETO' => Http::sequence()->push($order)->push([
                ...$order, 'status' => 'processed', 'status_detail' => 'accredited', 'total_paid_amount' => '50.00',
            ]),
        ]);
        $url = '/api/pedidos/'.$pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'boleto'])->assertAccepted()->assertJsonPath('data.processamento', 'pendente');
        Http::assertNothingSent();
        $pagamento = PedidoPagamento::sole();
        Queue::assertPushed(ProcessarPedidoPagamento::class, fn ($job) => $job->pagamentoId === $pagamento->id);
        $this->assertStringNotContainsString('12345678909', $pagamento->getRawOriginal('dados_envio'));
        $job = (new ProcessarPedidoPagamento($pagamento->id))->withFakeQueueInteractions();
        $job->handle(app(MercadoPagoService::class));
        $job->assertReleased(30);
        $this->getJson($url)->assertOk()->assertJsonPath('data.instrucoes.digitable_line', '23793380296060054351030006333303799140000020000')
            ->assertJsonPath('data.instrucoes.ticket_url', 'https://example.test/boleto')->assertJsonPath('data.instrucoes.barcode_content', 'CODIGO-FICTICIO')
            ->assertDontSee('NAO-EXPOR')->assertDontSee('12345678909');
        $this->assertSame(StatusPedido::AguardandoPagamento, $pedido->fresh()->status);
        $this->assertNull($pagamento->fresh()->dados_envio);
        $this->assertStringNotContainsString('23793380296060054351030006333303799140000020000', $pagamento->fresh()->getRawOriginal('instrucoes'));
        $this->postJson($url, ['meio' => 'boleto'])->assertAccepted();
        $this->postJson('/api/pedidos/'.$pedido->id.'/cancelar')->assertConflict();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['total_amount'] === '50.00'
            && $r['payer']['first_name'] === 'Maria' && $r['payer']['last_name'] === 'da Silva'
            && $r['payer']['address'] === ['street_name' => 'Rua do Pedido', 'street_number' => '10', 'zip_code' => '01001000', 'neighborhood' => 'Centro', 'city' => 'São Paulo', 'state' => 'SP']
            && $r['payer']['identification'] === ['type' => 'CPF', 'number' => '12345678909']
            && $r['transactions']['payments'][0]['payment_method'] === ['id' => 'boleto', 'type' => 'ticket']
            && $r->hasHeader('X-Idempotency-Key', $pagamento->idempotency_key));
        $ts = (string) now()->getTimestampMs();
        $assinatura = hash_hmac('sha256', "id:ORDBOLETO;request-id:boleto-teste;ts:$ts;", 'segredo-ficticio');
        $this->withHeaders(['x-request-id' => 'boleto-teste', 'x-signature' => "ts=$ts,v1=$assinatura"])
            ->postJson('/api/webhooks/mercado-pago?data.id=ORDBOLETO', ['type' => 'order'])->assertOk();
        $this->assertSame(StatusPedido::Pago, $pedido->fresh()->status);
        $this->getJson($url)->assertOk()->assertJsonPath('data.processamento', 'concluido');
        $this->assertDatabaseCount('pedido_pagamentos', 1);
        Http::assertSentCount(3);
    }

    public function test_boleto_com_snapshot_incompleto_nao_cria_cobranca(): void
    {
        $pedido = $this->pedido('boleto');
        $url = '/api/pedidos/'.$pedido->id.'/pagamento';
        $this->postJson($url, ['meio' => 'boleto'])->assertUnprocessable()->assertJsonValidationErrors('documento', 'data.errors');
        $this->postJson($url, ['meio' => 'pix'])->assertUnprocessable();
        $this->getJson($url)->assertNotFound();
        $this->assertSame(StatusPedido::AguardandoPagamento, $pedido->fresh()->status);
        $this->assertDatabaseCount('pedido_pagamentos', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
