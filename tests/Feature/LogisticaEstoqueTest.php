<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Usuario;
use App\Services\CarrinhoService;
use App\Services\ReservaEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class LogisticaEstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function entrar(array $paginas): Usuario
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => $paginas]);
        Passport::actingAs($usuario, ['admin:manage']);
        return $usuario;
    }

    private function produto(): Produto
    {
        return Produto::create(['codigo' => 'LOG-1', 'nome' => 'Produto logística', 'categoria' => 'camiseta', 'ativo' => true]);
    }

    private function estoque(float $disponivel = 10): void
    {
        $this->mock(CarrinhoService::class)->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(fn ($acao) => $acao());
        $this->mock(ReservaEstoqueService::class)->shouldReceive('contarDisponivel')->andReturn($disponivel);
    }

    public function test_estoque_e_manutencao_registram_entradas_e_saidas_com_status_de_ajuste(): void
    {
        $this->estoque();
        $produto = $this->produto();
        foreach (['estoque', 'manutencao'] as $perfil) {
            $this->entrar([$perfil]);
            foreach (['entrada', 'saida'] as $direcao) {
                $this->postJson('/api/admin/estoque/movimentacoes', [
                    'produto_id' => $produto->id, 'direcao' => $direcao, 'quantidade' => 2,
                ])->assertCreated();
            }
        }
        $this->assertDatabaseCount('produto_movimentacoes', 4);
        $this->assertDatabaseHas('notas_estoque', ['status' => 'Ajuste de Estoque']);
    }

    public function test_contabilidade_exige_nota_e_nao_pode_fazer_ajuste_ou_saida(): void
    {
        $this->estoque();
        $this->entrar(['contabilidade']);
        $dados = ['produto_id' => $this->produto()->id, 'direcao' => 'entrada', 'quantidade' => 1, 'tipo' => 'nota'];
        $this->postJson('/api/admin/estoque/movimentacoes', $dados)->assertUnprocessable()->assertJsonValidationErrors('nota', 'data.errors');
        $this->postJson('/api/admin/estoque/movimentacoes', $dados + ['nota' => 'NF-FICTICIA-123'])->assertCreated();
        $this->assertDatabaseHas('notas_estoque', ['referencia' => 'NF-FICTICIA-123', 'status' => 'Nota a verificar']);
        $this->postJson('/api/admin/estoque/movimentacoes', array_replace($dados, ['direcao' => 'saida', 'nota' => '123']))->assertForbidden();
        $this->postJson('/api/admin/estoque/movimentacoes', array_replace($dados, ['tipo' => 'ajuste']))->assertForbidden();
        $this->entrar(['estoque']);
        $this->postJson('/api/admin/estoque/movimentacoes', $dados + ['nota' => '123'])->assertForbidden();
    }

    public function test_saida_insuficiente_nao_persiste_movimentacao(): void
    {
        $this->estoque(0);
        $this->entrar(['manutencao']);
        $this->postJson('/api/admin/estoque/movimentacoes', [
            'produto_id' => $this->produto()->id, 'direcao' => 'saida', 'quantidade' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('quantidade', 'data.errors');
        $this->assertDatabaseCount('notas_estoque', 0);
        $this->assertDatabaseCount('produto_movimentacoes', 0);
    }

    public function test_historico_e_paginado_filtrado_por_produto_e_protegido(): void
    {
        $this->estoque();
        $this->entrar(['manutencao']);
        $produto = $this->produto();
        foreach ([1, 2, 3] as $quantidade) {
            $this->postJson('/api/admin/estoque/movimentacoes', [
                'produto_id' => $produto->id, 'direcao' => 'entrada', 'quantidade' => $quantidade,
            ])->assertCreated();
        }
        $outro = Produto::create(['codigo' => 'LOG-2', 'nome' => 'Outro produto', 'categoria' => 'camiseta']);
        $this->getJson('/api/admin/logistica/movimentacoes?produto_id='.$produto->id.'&per_page=2&page=2')
            ->assertOk()->assertJsonPath('data.total', 3)->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.quantidade', '1.000');
        $this->getJson('/api/admin/logistica/movimentacoes?produto_id='.$outro->id)->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/admin/logistica/movimentacoes')->assertUnprocessable();
        $this->entrar(['usuarios']);
        $this->getJson('/api/admin/logistica/produtos')->assertForbidden();
        $this->getJson('/api/admin/logistica/movimentacoes?produto_id='.$produto->id)->assertForbidden();
    }

    public function test_tela_logistica_aceita_os_tres_perfis_e_proxy_exige_oauth(): void
    {
        foreach (['estoque', 'manutencao', 'contabilidade'] as $perfil) {
            $usuario = $this->entrar([$perfil]);
            $this->actingAs($usuario, 'dashboard');
            $this->get('/dashboard/logistica')->assertOk();
            $this->getJson('/dashboard/api/admin/logistica/produtos')->assertUnauthorized();
        }
    }
}
