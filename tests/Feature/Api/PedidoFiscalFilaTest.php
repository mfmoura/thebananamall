<?php

namespace Tests\Feature\Api;

use App\Enums\StatusPedido;
use App\Jobs\ProcessarPedidoFiscal;
use App\Models\Carrinho;
use App\Models\FormaPagamento;
use App\Models\NotaFiscal;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\Usuario;
use App\Services\CarrinhoService;
use App\Services\ContoraNotaFiscalService;
use App\Services\PedidoFiscalService;
use App\Services\PedidoService;
use App\Services\ProdutoMovimentacaoService;
use App\Services\ViaCepService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PedidoFiscalFilaTest extends TestCase
{
    use DatabaseMigrations;

    private Usuario $usuario;

    private Produto $produto;

    private int $pagamento;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Http::preventStrayRequests();
        config(['services.fiscal_contora.cnpj' => '12345678000199', 'services.fiscal_contora.uf_emitente' => 'SP', 'services.fiscal_contora.serie' => 1, 'services.fiscal_contora.ambiente' => 'homologacao']);
        $this->usuario = Usuario::factory()->create(['email_validado' => true]);
        $cliente = $this->usuario->cliente()->create();
        Passport::actingAs($this->usuario, ['orders:write']);
        $this->produto = Produto::query()->create([
            'codigo' => 'LCB-TESTE', 'nome' => 'Bandeira LCB', 'categoria' => 'bandeira', 'ativo' => true,
            'configuracao_fiscal' => ['ncm' => '63079090', 'cfop_interno' => '5102', 'cfop_interestadual' => '6102', 'unidade' => 'UN', 'tributos' => ['icms' => ['code' => '102', 'origin' => 0]]],
        ]);
        $this->produto->precos()->create(['preco' => 25, 'data' => now()]);
        $this->pagamento = FormaPagamento::query()->create(['codigo' => 'pix', 'nome' => 'PIX', 'ativo' => true])->id;
        $carrinho = Carrinho::paraCliente($cliente->id)->adicionarProduto($this->produto->id, 1, '25.00');
        $this->mock(CarrinhoService::class, function ($mock) use ($carrinho): void {
            $mock->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(fn ($callback) => $callback());
            $mock->shouldReceive('consultar')->andReturn($carrinho);
            $mock->shouldReceive('removerSobBloqueio')->andReturn(true);
        });
        $this->mock(ProdutoMovimentacaoService::class, function ($mock): void {
            $mock->shouldReceive('registrarSaidaDoPedido')->andReturn(new ProdutoMovimentacao);
            $mock->shouldReceive('estornarSaidasDoPedido')->andReturnNull();
        });
        $this->mock(ViaCepService::class)->shouldReceive('confirmarEndereco')->andReturnNull();
        $this->mock(ContoraNotaFiscalService::class);
    }

    public function test_compra_retorna_processando_sem_chamar_provedor_ou_emitir_antes_do_pagamento(): void
    {
        $response = $this->checkout()->assertCreated()->assertJsonPath('data.processamento_fiscal.status', 'processando')
            ->assertJsonPath('data.processamento_fiscal.mensagem', 'Seus dados estão sendo processados.');
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $pedido = Pedido::query()->findOrFail($response->json('data.id'));
        app(PedidoFiscalService::class)->processar($pedido->processamentoFiscal->id);
        $this->assertSame('aguardando_pagamento', $pedido->processamentoFiscal->fresh()->etapa);
        $this->assertStringNotContainsString('12345678909', $pedido->processamentoFiscal->getRawOriginal('dados_cliente'));
    }

    public function test_pagamento_confirmado_enfileira_uma_vez_na_conexao_redis(): void
    {
        $pedido = $this->pedido();
        $service = app(PedidoService::class);
        $this->assertTrue($service->registrarPagamentoConfirmado($pedido));
        $this->assertTrue($service->registrarPagamentoConfirmado($pedido));
        Queue::assertPushed(ProcessarPedidoFiscal::class, 1);
        Queue::assertPushed(ProcessarPedidoFiscal::class, fn ($job) => $job->connection === 'redis' && $job->queue === 'fiscal'
            && ! str_contains(serialize($job), '12345678909'));
    }

    public function test_rollback_da_confirmacao_nao_enfileira_nota(): void
    {
        $pedido = $this->pedido();
        DB::beginTransaction();
        $pedido->update(['status' => StatusPedido::Pago]);
        Queue::assertNothingPushed();
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertSame(StatusPedido::AguardandoPagamento, $pedido->fresh()->status);
    }

    public function test_worker_usa_preco_e_tributacao_do_pedido_e_conclui_uma_unica_nota(): void
    {
        $pedido = $this->pedido();
        $pedido->update(['status' => StatusPedido::Pago]);
        $this->produto->precos()->create(['preco' => 99, 'data' => now()->addSecond()]);
        $this->produto->update(['configuracao_fiscal' => null]);
        $this->mock(ContoraNotaFiscalService::class, function ($mock) use ($pedido): void {
            $mock->shouldReceive('emitir')->once()->andReturnUsing(function ($usuario, $dados) use ($pedido) {
                $this->assertSame(25.0, (float) $dados['itens'][0]['valor_unitario']);
                $this->assertSame('63079090', $dados['itens'][0]['ncm']);
                $this->assertSame('5102', $dados['itens'][0]['cfop']);
                $this->assertSame('17', $dados['pagamentos'][0]['meio']);

                return NotaFiscal::query()->create(['pedido_id' => $pedido->id, 'provedor' => 'contora', 'numero' => '1', 'serie' => '1', 'status' => 'autorizada', 'tipo_operacao' => 'saida', 'natureza_operacao' => 'venda', 'caminho_danfe' => 'fiscal/teste.pdf']);
            });
            $mock->shouldReceive('sincronizar')->once()->andReturnUsing(fn ($nota) => $nota);
        });
        $service = app(PedidoFiscalService::class);
        $id = $pedido->processamentoFiscal->id;
        $this->assertNull($service->processar($id));
        $this->assertNull($service->processar($id));
        $this->assertSame('concluido', $pedido->processamentoFiscal->fresh()->etapa);
        $this->assertDatabaseCount('notas_fiscais', 1);
        $this->getJson('/api/pedidos/'.$pedido->id)->assertOk()->assertJsonPath('data.processamento_fiscal.status', 'concluido');
    }

    public function test_falta_de_tributacao_aguarda_configuracao_e_nao_inventa_dados(): void
    {
        $this->produto->update(['configuracao_fiscal' => null]);
        $pedido = $this->pedido();
        $pedido->update(['status' => StatusPedido::Pago]);
        app(PedidoFiscalService::class)->processar($pedido->processamentoFiscal->id);
        $this->assertSame('aguardando_configuracao', $pedido->processamentoFiscal->fresh()->etapa);
        $this->assertDatabaseCount('notas_fiscais', 0);
    }

    public function test_cancelamento_antes_de_pagar_impede_emissao(): void
    {
        $pedido = $this->pedido();
        $this->postJson('/api/pedidos/'.$pedido->id.'/cancelar')->assertOk();
        app(PedidoFiscalService::class)->processar($pedido->processamentoFiscal->id);
        $this->assertSame('cancelado', $pedido->processamentoFiscal->fresh()->etapa);
        Queue::assertNothingPushed();
    }

    public function test_download_do_pedido_exige_propriedade_e_nota_pronta(): void
    {
        $pedido = $this->pedido();
        $this->getJson('/api/pedidos/'.$pedido->id.'/danfe')->assertConflict();
        $outro = Usuario::factory()->create(['email_validado' => true]);
        $outro->cliente()->create();
        Passport::actingAs($outro, ['orders:write']);
        $this->getJson('/api/pedidos/'.$pedido->id.'/danfe')->assertForbidden();
    }

    public function test_falha_ao_publicar_na_fila_nao_perde_pagamento_e_outbox_recupera(): void
    {
        $pedido = $this->pedido();
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('Redis temporariamente indisponível'));
        $this->assertTrue(app(PedidoService::class)->registrarPagamentoConfirmado($pedido));
        $this->assertSame(StatusPedido::Pago, $pedido->fresh()->status);
        Bus::fake();
        $this->assertSame(1, app(PedidoFiscalService::class)->retomarPendentes());
        Bus::assertDispatched(ProcessarPedidoFiscal::class);
    }

    public function test_horizon_nao_fica_aberto_no_ambiente_local(): void
    {
        $this->getJson('/horizon/api/stats')->assertForbidden();
    }

    private function checkout(): TestResponse
    {
        $exemplo = json_decode(file_get_contents(base_path('docs/exemplos/contora-emissao.json')), true);

        return $this->withHeader('Idempotency-Key', '33333333-3333-4333-8333-333333333333')
            ->postJson('/api/pedidos', ['forma_pagamento_id' => $this->pagamento, 'dados_fiscais' => $exemplo['destinatario']]);
    }

    private function pedido(): Pedido
    {
        return Pedido::query()->findOrFail($this->checkout()->assertCreated()->json('data.id'));
    }
}
