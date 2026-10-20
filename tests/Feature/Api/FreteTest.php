<?php

namespace Tests\Feature\Api;

use App\Models\Carrinho;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Usuario;
use App\Services\CarrinhoService;
use App\Services\ProdutoMovimentacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class FreteTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;
    private Produto $produto;
    private Carrinho $carrinho;
    private int $forma;
    private bool $hubFalha = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['frete.token' => 'segredo-hub', 'frete.cep_origem' => '03044000']);
        Http::preventStrayRequests();
        Http::fake(['ws.hubdodesenvolvedor.com.br/*' => fn () => Http::response($this->hubFalha
            ? ['status' => false, 'return' => 'NOK', 'message' => 'erro do provedor']
            : ['status' => true, 'return' => 'OK', 'result' => ['valor_total' => 'R$ 25,80', 'prazo_de_entrega' => 'Dia da Postagem + 5 dias úteis']])]);
        $this->usuario = Usuario::factory()->create(['email_validado' => true]);
        $cliente = $this->usuario->cliente()->create();
        Passport::actingAs($this->usuario, ['orders:write']);
        $this->produto = Produto::create(['codigo' => 'FRETE-TEST', 'nome' => 'Teste', 'categoria' => 'bandeira',
            'ativo' => true, 'peso_gramas' => 500, 'altura_cm' => 10, 'largura_cm' => 15, 'comprimento_cm' => 20]);
        $this->produto->precos()->create(['preco' => 25, 'data' => now()]);
        $this->forma = FormaPagamento::create(['codigo' => 'pix', 'nome' => 'Pix', 'ativo' => true])->id;
        $this->carrinho = Carrinho::paraCliente($cliente->id)->adicionarProduto($this->produto->id, 1, '25.00');
        $this->mock(CarrinhoService::class, function ($mock): void {
            $mock->shouldReceive('consultar')->andReturnUsing(fn () => $this->carrinho);
            $mock->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(fn ($callback) => $callback());
            $mock->shouldReceive('validarParaCheckout')->andReturnNull();
            $mock->shouldReceive('removerSobBloqueio')->andReturn(true);
        });
        $this->mock(ProdutoMovimentacaoService::class)->shouldReceive('registrarSaidaDoPedido')->andReturn(new \App\Models\ProdutoMovimentacao);
    }

    private function cotar(): array
    {
        return $this->postJson('/api/pedidos/frete', ['cep' => '01001000'])->assertOk()->json('data');
    }

    private function comprar(array $cotacao, array $extras = [])
    {
        $dados = json_decode(file_get_contents(base_path('docs/exemplos/contora-emissao.json')), true);

        return $this->withHeader('Idempotency-Key', '33333333-3333-4333-8333-333333333333')->postJson('/api/pedidos', [
            'forma_pagamento_id' => $this->forma, 'dados_fiscais' => $dados['destinatario'],
            'frete_cotacao_id' => $cotacao['cotacao_id'], 'frete_servico' => '04510', ...$extras,
        ]);
    }

    public function test_cota_pac_sedex_converte_peso_e_nao_expoe_token(): void
    {
        $this->postJson('/api/pedidos/frete', ['cep' => '01001000'])->assertOk()
            ->assertJsonPath('data.opcoes.0.valor', '25.80')->assertJsonCount(2, 'data.opcoes')->assertDontSee('segredo-hub');
        Http::assertSent(fn ($request) => $request['peso'] == 0.5 && $request['cepOrigem'] === '03044000' && $request['altura'] === 10);
        $this->cotar();
        Http::assertSentCount(2);
    }

    public function test_checkout_salva_frete_total_e_retentativa_preserva_mesmo_apos_expirar(): void
    {
        $cotacao = $this->cotar();
        $resposta = $this->comprar($cotacao)->assertCreated()->assertJsonPath('data.subtotal', '25.00')
            ->assertJsonPath('data.custo_frete', '25.80')->assertJsonPath('data.total', '50.80')
            ->assertJsonPath('data.frete.nome', 'PAC');
        $this->travel(6)->minutes();
        $this->comprar($cotacao)->assertCreated()->assertJsonPath('data.id', $resposta->json('data.id'));
        Http::assertSentCount(2);
        $this->assertDatabaseCount('pedidos', 1);
    }

    public function test_pacote_soma_peso_altura_e_quantidades(): void
    {
        $this->carrinho = $this->carrinho->alterarQuantidade($this->produto->id, 2);
        $cotacao = $this->cotar();
        $this->assertSame(1000, $cotacao['pacote']['peso_gramas']);
        $this->assertSame(20, $cotacao['pacote']['altura_cm']);
        $this->assertSame(15, $cotacao['pacote']['largura_cm']);
        Http::assertSent(fn ($request) => $request['peso'] == 1);
    }

    public function test_cotacao_e_acessivel_via_bff_sem_credencial_no_browser(): void
    {
        config(['bff.client_secret' => str_repeat('s', 64)]);
        $this->seed(\Database\Seeders\BffClientSeeder::class);
        $this->postJson('/oauth/login', ['login' => $this->usuario->login, 'password' => 'password'])->assertOk();
        $url = $this->postJson('/bff/authorize')->assertOk()->json('authorization_url');
        $callback = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->get($callback)->assertRedirect('/oauth/callback');
        $this->postJson('/bff/api/pedidos/frete', ['cep' => '01001000'])->assertOk()
            ->assertJsonPath('data.opcoes.0.nome', 'PAC')->assertDontSee('segredo-hub');
    }

    public function test_rejeita_frete_adulterado_expirado_ou_carrinho_alterado(): void
    {
        $cotacao = $this->cotar();
        $this->comprar($cotacao, ['custo_frete' => 0])->assertUnprocessable();
        $this->comprar($cotacao, ['frete_servico' => '99999'])->assertUnprocessable();
        $this->carrinho = $this->carrinho->alterarQuantidade($this->produto->id, 2);
        $this->comprar($cotacao)->assertUnprocessable();
        $this->carrinho = $this->carrinho->alterarQuantidade($this->produto->id, 1);
        $this->produto->update(['peso_gramas' => 600]);
        $this->comprar($cotacao)->assertUnprocessable();
        $this->produto->update(['peso_gramas' => 500]);
        $this->travel(6)->minutes();
        $this->comprar($cotacao)->assertUnprocessable();
        $this->assertDatabaseCount('pedidos', 0);
    }

    public function test_cotacao_nao_pode_ser_usada_por_outro_cliente_ou_outro_cep(): void
    {
        $cotacao = $this->cotar();
        $outro = Usuario::factory()->create(['email_validado' => true]);
        $outro->cliente()->create();
        Passport::actingAs($outro, ['orders:write']);
        $this->comprar($cotacao)->assertUnprocessable();
        Passport::actingAs($this->usuario, ['orders:write']);
        $dados = json_decode(file_get_contents(base_path('docs/exemplos/contora-emissao.json')), true)['destinatario'];
        $dados['endereco']['cep'] = '20040002';
        $this->comprar($cotacao, ['dados_fiscais' => $dados])->assertUnprocessable();
    }

    public function test_falha_do_provedor_ou_ausencia_de_medidas_nao_vira_frete_gratis(): void
    {
        $this->hubFalha = true;
        $this->postJson('/api/pedidos/frete', ['cep' => '01001000'])->assertStatus(503)->assertDontSee('erro do provedor')->assertDontSee('segredo-hub');
        $this->produto->update(['peso_gramas' => null]);
        $this->postJson('/api/pedidos/frete', ['cep' => '01001000'])->assertUnprocessable();
        $this->postJson('/api/pedidos/frete', ['cep' => 'invalido'])->assertUnprocessable();
        Passport::actingAs($this->usuario, ['catalog:read']);
        $this->postJson('/api/pedidos/frete', ['cep' => '01001000'])->assertForbidden();
    }
}
