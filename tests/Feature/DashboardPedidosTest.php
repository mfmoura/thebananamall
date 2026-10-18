<?php

namespace Tests\Feature;

use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Services\CarrinhoService;
use App\Services\ProdutoMovimentacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DashboardPedidosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $paginas = ['pedidos'], array $scopes = ['admin:manage']): void
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => $paginas]);
        Passport::actingAs($usuario, $scopes);
    }

    private function pedido(string $status = 'aguardando_pagamento'): Pedido
    {
        $cliente = Usuario::factory()->create()->cliente()->create([]);
        $forma = FormaPagamento::firstOrCreate(['codigo' => 'pix'], ['nome' => 'PIX', 'ativo' => true]);

        return Pedido::create(['cliente_id' => $cliente->id, 'forma_pagamento_id' => $forma->id,
            'status' => $status, 'data' => now(), 'idempotency_key' => fake()->uuid()]);
    }

    public function test_consulta_filtra_e_nao_permite_edicao_criacao_ou_exclusao(): void
    {
        $pedido = $this->pedido();
        $this->pedido('cancelado');
        $this->admin();
        $this->getJson('/api/admin/pedidos?status=aguardando_pagamento')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/admin/pedidos?pedido_id='.$pedido->id)->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/admin/pedidos/'.$pedido->id)->assertOk()->assertJsonPath('data.pode_cancelar', true)->assertJsonMissingPath('data.pedido.idempotency_key');
        $this->getJson('/api/admin/pedidos?status=invalido')->assertUnprocessable();
        $this->getJson('/api/admin/pedidos?per_page=100')->assertUnprocessable();
        $this->postJson('/api/admin/pedidos')->assertStatus(405);
        $this->patchJson('/api/admin/pedidos/'.$pedido->id, ['status' => 'pago'])->assertStatus(405);
        $this->deleteJson('/api/admin/pedidos/'.$pedido->id)->assertStatus(405);
    }

    public function test_exige_perfil_escopo_e_permissao_pedidos(): void
    {
        $this->getJson('/api/admin/pedidos')->assertUnauthorized();
        $this->admin(['estoque']);
        $this->getJson('/api/admin/pedidos')->assertForbidden();
        $this->admin(['pedidos'], ['catalog:read']);
        $this->getJson('/api/admin/pedidos')->assertForbidden();
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->cliente()->create([]);
        Passport::actingAs($usuario, ['admin:manage']);
        $this->getJson('/api/admin/pedidos')->assertForbidden();
    }

    public function test_cancela_preservando_historico_e_rejeita_pedido_pago(): void
    {
        $this->mock(CarrinhoService::class)->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(fn ($callback) => $callback());
        $this->mock(ProdutoMovimentacaoService::class)->shouldReceive('estornarSaidasDoPedido')->once()->andReturnNull();
        $pedido = $this->pedido();
        $pago = $this->pedido('pago');
        $this->admin();
        $this->postJson('/api/admin/pedidos/'.$pedido->id.'/cancelar')->assertOk()->assertJsonPath('data.pedido.status', 'cancelado');
        $this->postJson('/api/admin/pedidos/'.$pedido->id.'/cancelar')->assertConflict();
        $this->postJson('/api/admin/pedidos/'.$pago->id.'/cancelar')->assertConflict();
        $this->assertDatabaseHas('pedidos', ['id' => $pedido->id, 'deleted_at' => null]);
    }

    public function test_etiqueta_usa_snapshot_sem_documento_e_danfe_exige_processamento(): void
    {
        $pedido = $this->pedido('pago');
        $pedido->processamentoFiscal()->create(['chave' => fake()->uuid(), 'etapa' => 'pendente',
            'dados_cliente' => ['nome' => 'Cliente Teste', 'documento' => 'SIGILOSO', 'endereco' => ['logradouro' => 'Rua do Pedido', 'numero' => '1', 'cidade' => 'São Paulo', 'estado' => 'SP', 'cep' => '01001000']]]);
        $this->admin();
        $this->getJson('/api/admin/pedidos/'.$pedido->id.'/etiqueta')->assertOk()->assertJsonPath('data.nome', 'Cliente Teste')->assertDontSee('SIGILOSO');
        $this->getJson('/api/admin/pedidos/'.$pedido->id)->assertOk()->assertDontSee('SIGILOSO');
        $this->getJson('/api/admin/pedidos/'.$pedido->id.'/danfe')->assertConflict();
        $aguardando = $this->pedido();
        $this->getJson('/api/admin/pedidos/'.$aguardando->id.'/etiqueta')->assertConflict();
    }

    public function test_cobranca_inclusive_excluida_impede_cancelamento(): void
    {
        $this->mock(CarrinhoService::class)->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(fn ($callback) => $callback());
        $pedido = $this->pedido();
        $pagamento = \App\Models\PedidoPagamento::create(['pedido_id' => $pedido->id,
            'idempotency_key' => fake()->uuid(), 'requisicao_hash' => hash('sha256', 'teste')]);
        $pagamento->delete();
        $this->admin();
        $this->getJson('/api/admin/pedidos/'.$pedido->id)->assertOk()->assertJsonPath('data.pode_cancelar', false);
        $this->postJson('/api/admin/pedidos/'.$pedido->id.'/cancelar')->assertConflict();
    }

    public function test_proxy_permite_consulta_e_bloqueia_edicao(): void
    {
        config(['bff.client_secret' => str_repeat('s', 64)]);
        $this->seed(\Database\Seeders\BffClientSeeder::class);
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => ['pedidos']]);
        $pedido = $this->pedido();
        $this->postJson('/dashboard/login', ['login' => $usuario->login, 'password' => 'password'])->assertOk();
        $this->get('/dashboard/pedidos')->assertOk();
        $url = $this->postJson('/dashboard/authorize')->assertOk()->json('authorization_url');
        $callback = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->get($callback)->assertRedirect('/dashboard/');
        $this->getJson('/dashboard/api/admin/pedidos')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/dashboard/api/admin/pedidos/'.$pedido->id)->assertOk();
        $this->patchJson('/dashboard/api/admin/pedidos/'.$pedido->id, ['status' => 'pago'])->assertNotFound();
        $this->deleteJson('/dashboard/api/admin/pedidos/'.$pedido->id)->assertNotFound();
        $this->getJson('/dashboard/api/admin/usuarios')->assertForbidden();
    }
}
