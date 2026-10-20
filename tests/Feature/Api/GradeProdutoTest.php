<?php

namespace Tests\Feature\Api;

use App\Models\Carrinho;
use App\Models\FormaPagamento;
use App\Models\Produto;
use App\Models\Usuario;
use App\Services\CarrinhoService;
use App\Services\FreteService;
use App\Services\PedidoService;
use App\Services\ReservaEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Passport;
use Tests\TestCase;

class GradeProdutoTest extends TestCase
{
    use RefreshDatabase;

    private array $strings = [];
    private array $hashes = [];
    private Usuario $usuario;
    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::shouldReceive('get')->andReturnUsing(fn ($key) => $this->strings[$key] ?? null);
        Redis::shouldReceive('exists')->andReturnUsing(fn ($key) => isset($this->strings[$key]));
        Redis::shouldReceive('setex')->andReturnUsing(function ($key, $ttl, $value) { $this->strings[$key] = $value; return true; });
        Redis::shouldReceive('del')->andReturnUsing(function ($key) { $had = isset($this->strings[$key]); unset($this->strings[$key]); return (int) $had; });
        Redis::shouldReceive('hgetall')->andReturnUsing(fn ($key) => $this->hashes[$key] ?? []);
        Redis::shouldReceive('hget')->andReturnUsing(fn ($key, $field) => $this->hashes[$key][$field] ?? null);
        Redis::shouldReceive('hset')->andReturnUsing(function ($key, $field, $value) { $this->hashes[$key][$field] = $value; return 1; });
        Redis::shouldReceive('hdel')->andReturnUsing(function ($key, $field) { $had = isset($this->hashes[$key][$field]); unset($this->hashes[$key][$field]); return (int) $had; });
        $this->usuario = Usuario::factory()->create(['email_validado' => true]);
        $this->usuario->administrador()->create(['paginas' => ['estoque', 'contabilidade']]);
        $this->usuario->cliente()->create();
        Passport::actingAs($this->usuario, ['admin:manage', 'cart:write', 'cart:read', 'orders:write']);
        $this->produto = Produto::create(['codigo' => 'CAM-GRADE', 'nome' => 'Camiseta', 'categoria' => 'camiseta', 'ativo' => true, 'grade' => ['P', 'M', 'G']]);
        $this->produto->precos()->create(['preco' => '30.00', 'data' => now()]);
    }

    private function entrada(string $tamanho, int $quantidade): void
    {
        $this->postJson('/api/admin/estoque/movimentacoes', ['produto_id' => $this->produto->id,
            'tamanho' => $tamanho, 'quantidade' => $quantidade, 'direcao' => 'entrada'])->assertCreated();
    }

    public function test_estoque_e_consulta_separam_os_tamanhos_e_validam_movimentacoes(): void
    {
        $this->entrada('P', 3);
        $this->entrada('M', 1);
        $base = ['produto_id' => $this->produto->id, 'direcao' => 'saida', 'quantidade' => 2];
        $this->postJson('/api/admin/estoque/movimentacoes', $base + ['tamanho' => 'M'])->assertUnprocessable();
        $this->postJson('/api/admin/estoque/movimentacoes', $base)->assertUnprocessable();
        $this->postJson('/api/admin/estoque/movimentacoes', $base + ['tamanho' => 'GG'])->assertUnprocessable();
        $this->postJson('/api/admin/estoque/movimentacoes', array_replace($base, ['tamanho' => 'P', 'quantidade' => 0.5]))->assertUnprocessable();
        $this->getJson('/api/produtos/'.$this->produto->id)->assertOk()
            ->assertJsonPath('data.estoque', 4)->assertJsonPath('data.estoque_por_tamanho.0.estoque', 3)
            ->assertJsonPath('data.estoque_por_tamanho.1.estoque', 1);
    }

    public function test_carrinho_reserva_por_tamanho_sem_renovar_em_reenvio_identico(): void
    {
        $this->entrada('P', 3);
        $this->entrada('M', 2);
        $carrinhos = app(CarrinhoService::class);
        $cliente = $this->usuario->cliente;
        $carrinhos->adicionarProduto($cliente, $this->produto->id, 1, 'P');
        $carrinho = $carrinhos->adicionarProduto($cliente, $this->produto->id, 2, 'M');
        $this->assertCount(2, $carrinho->itens);
        $reserva = app(ReservaEstoqueService::class);
        $this->assertSame(3.0, $reserva->contarReservado($this->produto->id));
        $this->assertSame(1.0, $reserva->contarReservado($this->produto->id, null, 'P'));
        $this->assertSame(0.0, $reserva->contarDisponivel($this->produto, 'M'));
        $prazo = $carrinho->reserva_expira_em;
        $this->travel(1)->minutes();
        $carrinho = $carrinhos->atualizarItem($cliente, $this->produto->id, 2, 'M');
        $this->assertSame($prazo, $carrinho->reserva_expira_em);
        $carrinhos->excluirItem($cliente, $this->produto->id, 'P');
        $this->assertSame(0.0, $reserva->contarReservado($this->produto->id, null, 'P'));
        $this->assertSame(2.0, $reserva->contarReservado($this->produto->id, null, 'M'));
        $this->travel(16)->minutes();
        $this->assertSame(0.0, $reserva->contarReservado($this->produto->id));
    }

    public function test_checkout_e_cancelamento_preservam_tamanho_e_estornam_apenas_o_saldo_correto(): void
    {
        $this->entrada('P', 3);
        $this->entrada('M', 2);
        $cliente = $this->usuario->cliente;
        app(CarrinhoService::class)->adicionarProduto($cliente, $this->produto->id, 2, 'P');
        app(CarrinhoService::class)->adicionarProduto($cliente, $this->produto->id, 1, 'M');
        $this->mock(FreteService::class)->shouldReceive('confirmar')->andReturn(['valor' => '0.00']);
        $forma = FormaPagamento::create(['codigo' => 'pix', 'nome' => 'Pix', 'ativo' => true]);
        $service = app(PedidoService::class);
        $pedido = $service->comprar($cliente, $forma->id, 'grade-checkout', ['endereco' => ['cep' => '01001000']]);
        $this->assertEqualsCanonicalizing(['P', 'M'], $pedido->itens->pluck('tamanho')->all());
        $reserva = app(ReservaEstoqueService::class);
        $this->assertSame(1.0, $reserva->contarEstoque($this->produto, 'P'));
        $this->assertSame(1.0, $reserva->contarEstoque($this->produto, 'M'));
        $this->assertTrue($service->cancelar($cliente, $pedido));
        $this->assertSame(3.0, $reserva->contarEstoque($this->produto, 'P'));
        $this->assertSame(2.0, $reserva->contarEstoque($this->produto, 'M'));
        $this->assertFalse($service->cancelar($cliente, $pedido));
    }

    public function test_grade_permite_acrescentar_e_rejeita_remocao_e_duplicatas(): void
    {
        $url = '/api/admin/produtos/'.$this->produto->id;
        $this->patchJson($url, ['grade' => ['P', 'M', 'G', 'GG']])->assertOk();
        $this->patchJson($url, ['grade' => ['P', 'M']])->assertUnprocessable();
        $this->patchJson($url, ['grade' => ['P', 'p']])->assertUnprocessable();
        $this->patchJson($url, ['grade' => [' P']])->assertUnprocessable();
    }

    public function test_carrinho_exige_tamanho_da_grade(): void
    {
        $this->entrada('P', 1);
        $this->expectException(ValidationException::class);
        app(CarrinhoService::class)->adicionarProduto($this->usuario->cliente, $this->produto->id, 1);
    }

    public function test_api_altera_e_remove_somente_o_tamanho_selecionado(): void
    {
        $this->entrada('P', 3);
        $this->entrada('M', 2);
        $this->withHeader('Authorization', 'Bearer token-de-teste');
        $this->postJson('/api/carrinho/itens', ['codigo' => $this->produto->id, 'tamanho' => 'P'])->assertOk();
        $this->postJson('/api/carrinho/itens', ['codigo' => $this->produto->id, 'tamanho' => 'M'])->assertOk()->assertJsonCount(2, 'data.itens');
        $this->patchJson('/api/carrinho/itens/'.$this->produto->id, ['tamanho' => 'P', 'quantidade' => 2])
            ->assertOk()->assertJsonPath('data.itens.0.quantidade', 2)->assertJsonPath('data.itens.1.quantidade', 1);
        $this->deleteJson('/api/carrinho/itens/'.$this->produto->id.'?tamanho=M')->assertOk();
        $this->getJson('/api/carrinho')->assertOk()->assertJsonCount(1, 'data.itens')->assertJsonPath('data.itens.0.tamanho', 'P');
        $this->putJson('/api/carrinho', ['itens' => [
            ['codigo' => $this->produto->id, 'tamanho' => 'P', 'quantidade' => 2],
            ['codigo' => $this->produto->id, 'tamanho' => 'P', 'quantidade' => 2],
        ]])->assertUnprocessable();
    }

    public function test_reserva_de_outro_cliente_nao_consume_saldo_de_outro_tamanho(): void
    {
        $this->entrada('P', 1);
        $this->entrada('M', 1);
        $outro = Usuario::factory()->create()->cliente()->create();
        $carrinhos = app(CarrinhoService::class);
        $carrinhos->adicionarProduto($outro, $this->produto->id, 1, 'M');
        $carrinhos->adicionarProduto($this->usuario->cliente, $this->produto->id, 1, 'P');
        $this->expectException(ValidationException::class);
        $carrinhos->adicionarProduto($this->usuario->cliente, $this->produto->id, 1, 'M');
    }

    public function test_produto_sem_grade_preserva_quantidade_fracionada_e_historico(): void
    {
        $produto = Produto::create(['codigo' => 'SEM-GRADE', 'nome' => 'Sem grade', 'categoria' => 'outro', 'ativo' => true]);
        $this->postJson('/api/admin/estoque/movimentacoes', ['produto_id' => $produto->id, 'quantidade' => 1.5, 'direcao' => 'entrada'])->assertCreated();
        $this->patchJson('/api/admin/produtos/'.$produto->id, ['grade' => ['P', 'M']])->assertUnprocessable();
        $this->getJson('/api/produtos/'.$produto->id.'/estoque')->assertOk()->assertJsonPath('data.estoque', 1.5);
    }

    public function test_nota_fiscal_registra_saldos_e_descricao_por_tamanho(): void
    {
        $dados = ['natureza_operacao' => 'compra', 'provedor' => 'manual', 'referencia_provedor' => 'NF-GRADE',
            'numero' => '1', 'data_emissao' => now(), 'itens' => [
                ['produto_id' => $this->produto->id, 'tamanho' => 'P', 'quantidade' => 2, 'valor_unitario' => 10],
                ['produto_id' => $this->produto->id, 'tamanho' => 'M', 'quantidade' => 3, 'valor_unitario' => 10],
            ]];
        $nota = app(\App\Services\NotaFiscalService::class)->registrar($this->usuario, $dados);
        $this->assertEqualsCanonicalizing(['P', 'M'], $nota->itens->pluck('tamanho')->all());
        $this->assertStringContainsString('Tamanho M', $nota->itens->firstWhere('tamanho', 'M')->descricao);
        $this->assertSame(2.0, app(ReservaEstoqueService::class)->contarEstoque($this->produto, 'P'));
        $this->assertSame(3.0, app(ReservaEstoqueService::class)->contarEstoque($this->produto, 'M'));
    }

    public function test_mesclar_e_serializar_preserva_identidade_de_produto_e_tamanho(): void
    {
        $a = Carrinho::paraCliente(1)->adicionarProduto(1, 2, '30.00', 'P');
        $b = Carrinho::paraVisitante(1)->adicionarProduto(1, 1, '30.00', 'P')->adicionarProduto(1, 3, '30.00', 'M');
        $c = Carrinho::deArray($a->mesclar($b)->toArray());
        $this->assertCount(2, $c->itens);
        $this->assertSame('P', $c->itens[0]->tamanho);
        $this->assertSame(3.0, $c->itens[0]->quantidade());
        $this->assertSame('M', $c->itens[1]->tamanho);
    }
}
