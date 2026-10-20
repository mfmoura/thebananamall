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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MercadoPagoModalidadesTest extends TestCase
{
    use RefreshDatabase;

    private Pedido $pedido;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['mercadopago.access_token' => 'credencial-ficticia']);
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $cliente = $usuario->cliente()->create();
        Passport::actingAs($usuario, ['orders:write']);
        $forma = FormaPagamento::create(['codigo' => 'cartao_credito', 'nome' => 'Crédito', 'ativo' => true]);
        $produto = Produto::create(['codigo' => 'MODALIDADE-TESTE', 'nome' => 'Produto', 'categoria' => 'bandeira', 'ativo' => true]);
        $preco = $produto->precos()->create(['preco' => '19.90', 'data' => now()]);
        $this->pedido = Pedido::create(['cliente_id' => $cliente->id, 'forma_pagamento_id' => $forma->id,
            'status' => StatusPedido::AguardandoPagamento, 'data' => now(), 'idempotency_key' => fake()->uuid()]);
        $this->pedido->itens()->create(['codigo' => $produto->id, 'preco_id' => $preco->id, 'preco' => '19.90', 'quantidade' => 3, 'data' => now()]);
    }

    public static function cartoes(): array
    {
        // IDs são fixtures do contrato; não atestam habilitação comercial da conta.
        return [
            'credito visa' => ['credit_card', 'visa', 'cartao_credito'],
            'credito master' => ['credit_card', 'master', 'cartao_credito'],
            'credito elo' => ['credit_card', 'elo', 'cartao_credito'],
            'credito amex' => ['credit_card', 'amex', 'cartao_credito'],
            'debito visa' => ['debit_card', 'debvisa', 'cartao_debito'],
            'debito master' => ['debit_card', 'debmaster', 'cartao_debito'],
            'debito elo' => ['debit_card', 'debelo', 'cartao_debito'],
        ];
    }

    #[DataProvider('cartoes')]
    public function test_cartao_aceito_processa_fora_da_requisicao_e_confirma_uma_unica_cobranca(string $meio, string $bandeira, string $codigo): void
    {
        $this->forma($codigo);
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORDMODALIDADE'], 201),
            'api.mercadopago.com/v1/orders/ORDMODALIDADE' => Http::response($this->order()),
        ]);
        $dados = $this->cartao($meio, $bandeira);
        $this->postJson($this->url(), $dados)->assertAccepted()->assertJsonPath('data.processamento', 'pendente')->assertDontSee('token-ficticio');
        Http::assertNothingSent();
        $pagamento = PedidoPagamento::sole();
        Queue::assertPushed(ProcessarPedidoPagamento::class, fn ($job) => $job->pagamentoId === $pagamento->id);
        $this->assertStringNotContainsString('token-ficticio', $pagamento->getRawOriginal('dados_envio'));
        $job = (new ProcessarPedidoPagamento($pagamento->id))->withFakeQueueInteractions();
        $job->handle(app(MercadoPagoService::class));
        $job->assertNotReleased();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['total_amount'] === '59.70'
            && $r['transactions']['payments'][0]['amount'] === '59.70'
            && $r['transactions']['payments'][0]['payment_method'] === ['id' => $bandeira, 'type' => $meio, 'token' => 'token-ficticio', 'installments' => 1]
            && $r->hasHeader('X-Idempotency-Key', $pagamento->idempotency_key));
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        $this->assertNull($pagamento->fresh()->dados_envio);
        $this->postJson($this->url(), $dados)->assertAccepted();
        app(MercadoPagoService::class)->processar($pagamento->id);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.processamento', 'concluido')->assertDontSee('token-ficticio');
        $this->assertDatabaseCount('pedido_pagamentos', 1);
        Http::assertSentCount(2);
    }

    public static function resultados(): array
    {
        $casos = [];
        foreach (['credit_card', 'debit_card'] as $meio) {
            foreach (['failed', 'canceled', 'expired', 'refunded', 'charged_back'] as $status) {
                $casos["$meio $status"] = [$meio, $status, 'concluido'];
            }
            $casos["$meio pendente"] = [$meio, 'action_required', 'aguardando'];
        }
        return $casos;
    }

    #[DataProvider('resultados')]
    public function test_resultado_nao_aprovado_nao_confirma_pedido_nem_permite_trocar_token(string $meio, string $status, string $processamento): void
    {
        $this->forma($meio === 'credit_card' ? 'cartao_credito' : 'cartao_debito');
        $dados = $this->cartao($meio);
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORDMODALIDADE'], 201),
            'api.mercadopago.com/v1/orders/ORDMODALIDADE' => Http::response($this->order(['status' => $status, 'status_detail' => 'recusado-ou-pendente', 'total_paid_amount' => '0.00'])),
        ]);
        $this->postJson($this->url(), $dados)->assertAccepted();
        $pagamento = PedidoPagamento::sole();
        $job = (new ProcessarPedidoPagamento($pagamento->id))->withFakeQueueInteractions();
        $job->handle(app(MercadoPagoService::class));
        if ($processamento === 'aguardando') $job->assertReleased(30);
        else $job->assertNotReleased();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.status', $status)->assertJsonPath('data.processamento', $processamento);
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        $this->postJson($this->url(), [...$dados, 'token' => 'outro-token'])->assertConflict();
        $this->postJson('/api/pedidos/'.$this->pedido->id.'/cancelar')->assertConflict();
        $this->assertDatabaseCount('pedido_pagamentos', 1);
        Http::assertSentCount(2);
    }

    public function test_recusa_402_preserva_order_consulta_e_conclui_sem_repetir_cobranca(): void
    {
        $recusa = $this->order(['status' => 'failed', 'status_detail' => 'failed']);
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['errors' => [['code' => 'failed']], 'data' => $recusa], 402),
            'api.mercadopago.com/v1/orders/ORDMODALIDADE' => Http::response($recusa),
        ]);
        $this->postJson($this->url(), $this->cartao())->assertAccepted();
        $pagamento = PedidoPagamento::sole();
        app(MercadoPagoService::class)->processar($pagamento->id);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.order_id', 'ORDMODALIDADE')->assertJsonPath('data.processamento', 'concluido');
        $this->assertNull($pagamento->fresh()->dados_envio);
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        app(MercadoPagoService::class)->processar($pagamento->id);
        Http::assertSentCount(2);
    }

    public function test_recusa_402_com_falha_na_consulta_retoma_get_sem_reenviar_token(): void
    {
        $recusa = $this->order(['status' => 'failed', 'status_detail' => 'failed']);
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['data' => $recusa], 402),
            'api.mercadopago.com/v1/orders/ORDMODALIDADE' => Http::sequence()->pushFailedConnection()->push($recusa),
        ]);
        $this->postJson($this->url(), $this->cartao())->assertAccepted();
        $pagamento = PedidoPagamento::sole();
        try {
            app(MercadoPagoService::class)->processar($pagamento->id);
            $this->fail('A consulta deve falhar.');
        } catch (HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        $this->assertSame('ORDMODALIDADE', $pagamento->fresh()->order_id);
        $this->assertNull($pagamento->fresh()->dados_envio);
        app(MercadoPagoService::class)->processar($pagamento->id);
        $this->assertSame('concluido', $pagamento->fresh()->processamento);
        $this->assertCount(1, Http::recorded(fn ($r) => $r->method() === 'POST'));
    }

    public function test_402_sem_order_valida_continua_incerto_e_nao_confirma_pagamento(): void
    {
        Http::fake(['*' => Http::response(['data' => ['id' => '../invalido', 'status' => 'failed']], 402)]);
        $this->postJson($this->url(), $this->cartao())->assertAccepted();
        $pagamento = PedidoPagamento::sole();
        try {
            app(MercadoPagoService::class)->processar($pagamento->id);
            $this->fail('Resposta inválida deve ser rejeitada.');
        } catch (HttpException $e) { $this->assertSame(502, $e->getStatusCode()); }
        $this->assertNull($pagamento->fresh()->order_id);
        $this->assertNotNull($pagamento->fresh()->dados_envio);
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        $this->postJson($this->url(), [...$this->cartao(), 'token' => 'outro'])->assertConflict();
        Http::assertSentCount(1);
    }

    public function test_timeout_no_debito_repete_o_mesmo_snapshot_e_chave(): void
    {
        $this->forma('cartao_debito');
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::sequence()->pushFailedConnection()->push(['id' => 'ORDMODALIDADE'], 201),
            'api.mercadopago.com/v1/orders/ORDMODALIDADE' => Http::response($this->order()),
        ]);
        $this->postJson($this->url(), $this->cartao('debit_card', 'debvisa'))->assertAccepted();
        $pagamento = PedidoPagamento::sole();
        $snapshot = $pagamento->dados_envio;
        try {
            app(MercadoPagoService::class)->processar($pagamento->id);
            $this->fail('Timeout deveria ser propagado para retentativa.');
        } catch (HttpException $e) { $this->assertSame(503, $e->getStatusCode()); }
        $this->assertSame($snapshot, $pagamento->fresh()->dados_envio);
        $this->assertSame(StatusPedido::AguardandoPagamento, $this->pedido->fresh()->status);
        app(MercadoPagoService::class)->processar($pagamento->id);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->data() === $snapshot && $r->hasHeader('X-Idempotency-Key', $pagamento->idempotency_key));
        $this->assertSame(StatusPedido::Pago, $this->pedido->fresh()->status);
        $this->assertDatabaseCount('pedido_pagamentos', 1);
    }

    public static function dadosInvalidos(): array
    {
        return [
            'meio nao integrado' => [['meio' => 'dinheiro'], 'meio'],
            'debito sem token' => [['meio' => 'debit_card', 'payment_method_id' => 'debvisa', 'parcelas' => 1], 'token'],
            'credito sem bandeira' => [['meio' => 'credit_card', 'token' => 'ficticio', 'parcelas' => 1], 'payment_method_id'],
            'debito parcelado' => [['meio' => 'debit_card', 'payment_method_id' => 'debvisa', 'token' => 'ficticio', 'parcelas' => 2], 'parcelas'],
            'credito com cvv' => [['meio' => 'credit_card', 'payment_method_id' => 'visa', 'token' => 'ficticio', 'parcelas' => 1, 'cvv' => '123'], 'cvv'],
            'debito com valor adulterado' => [['meio' => 'debit_card', 'payment_method_id' => 'debvisa', 'token' => 'ficticio', 'parcelas' => 1, 'transaction_amount' => '0.01'], 'transaction_amount'],
        ];
    }

    #[DataProvider('dadosInvalidos')]
    public function test_payload_invalido_nao_persiste_nem_enfileira(array $dados, string $campo): void
    {
        $this->postJson($this->url(), $dados)->assertUnprocessable()->assertJsonValidationErrors($campo, 'data.errors');
        $this->assertDatabaseCount('pedido_pagamentos', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_credito_nao_pode_ser_usado_para_pedido_de_debito(): void
    {
        $this->forma('cartao_debito');
        $this->postJson($this->url(), $this->cartao())->assertUnprocessable();
        $this->assertDatabaseCount('pedido_pagamentos', 0);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    private function forma(string $codigo): void
    {
        $this->pedido->formaPagamento->update(['codigo' => $codigo]);
    }

    private function cartao(string $meio = 'credit_card', string $bandeira = 'visa'): array
    {
        return ['meio' => $meio, 'payment_method_id' => $bandeira, 'token' => 'token-ficticio', 'parcelas' => 1];
    }

    private function url(): string
    {
        return '/api/pedidos/'.$this->pedido->id.'/pagamento';
    }

    private function order(array $alteracoes = []): array
    {
        return [...['id' => 'ORDMODALIDADE', 'external_reference' => (string) $this->pedido->id,
            'country_code' => 'BRA', 'total_amount' => '59.70', 'total_paid_amount' => '59.70',
            'status' => 'processed', 'status_detail' => 'accredited'], ...$alteracoes];
    }
}
