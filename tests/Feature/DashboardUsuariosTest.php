<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\CarrinhoService;
use App\Services\EmailVerificationService;
use App\Services\PoliticaSenhaService;
use Database\Seeders\BffClientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DashboardUsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.password_pepper' => 'pepper-para-testes-de-usuarios']);
        $this->mock(EmailVerificationService::class)->shouldReceive('enviarConfirmacaoInicial')->byDefault()->andReturnNull();
        $this->mock(CarrinhoService::class)->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(fn ($callback) => $callback())
            ->shouldReceive('removerSobBloqueio')->andReturn(true);
    }

    private function administrador(array $paginas = ['usuarios']): Usuario
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => $paginas]);
        return $usuario;
    }

    private function payload(string $tipo = 'cliente'): array
    {
        return ['tipo' => $tipo, 'login' => 'nova-conta', 'email' => 'nova@example.test',
            'password' => 'V3rde!Azul27', 'password_confirmation' => 'V3rde!Azul27'];
    }

    private function tokenDoUsuario(Usuario $usuario): string
    {
        $id = bin2hex(random_bytes(40));
        Passport::token()->newInstance()->forceFill(['id' => $id, 'user_id' => $usuario->id,
            'client_id' => config('bff.client_id'), 'scopes' => ['catalog:read'], 'revoked' => false, 'expires_at' => now()->addHour()])->save();
        Passport::refreshToken()->newInstance()->forceFill(['id' => bin2hex(random_bytes(40)),
            'access_token_id' => $id, 'revoked' => false, 'expires_at' => now()->addDay()])->save();
        return $id;
    }

    public function test_lista_pagina_busca_e_filtra_sem_expor_dados_sensiveis(): void
    {
        $admin = $this->administrador();
        $cliente = Usuario::factory()->create(['login' => 'cliente-busca']);
        $cliente->cliente()->create(['dados_fiscais' => ['documento' => 'DOCUMENTO-SIGILOSO']]);
        $apagado = Usuario::factory()->create(); $apagado->delete();
        $visitante = Usuario::factory()->create(); $visitante->visitante()->create(['token_hash' => hash('sha256', 'visitante-teste'), 'expira_em' => now()->addDay()]);
        Passport::actingAs($admin, ['admin:manage']);
        $this->getJson('/api/admin/usuarios?per_page=1&page=2')->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.current_page', 2);
        $this->getJson('/api/admin/usuarios?busca=cliente-busca&tipo=cliente')->assertOk()->assertJsonPath('data.total', 1)
            ->assertJsonMissingPath('data.data.0.password')->assertJsonMissingPath('data.data.0.cliente.dados_fiscais')->assertDontSee('DOCUMENTO-SIGILOSO');
        $this->getJson('/api/admin/usuarios/'.$cliente->id)->assertOk()->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.enderecos');
        $this->getJson('/api/admin/usuarios/'.$visitante->id)->assertNotFound();
        $this->getJson('/api/admin/usuarios?per_page=100')->assertUnprocessable();
    }

    public function test_cadastra_cliente_administrador_e_logistica_com_senha_segura(): void
    {
        Passport::actingAs($this->administrador(['usuarios', 'estoque']), ['admin:manage']);
        $cliente = $this->postJson('/api/admin/usuarios', $this->payload())->assertCreated()->assertJsonPath('data.email_validado', false)->json('data.id');
        $this->assertTrue(app(PoliticaSenhaService::class)->corresponde('V3rde!Azul27', Usuario::find($cliente)->password));
        $this->assertDatabaseHas('clientes', ['usuario_id' => $cliente]);
        $this->postJson('/api/admin/usuarios', [...$this->payload('administrador'), 'login' => 'nova-admin', 'email' => 'admin2@example.test', 'paginas' => ['estoque']])
            ->assertCreated()->assertJsonPath('data.administrador.paginas', ['estoque']);
        $this->postJson('/api/admin/usuarios', [...$this->payload('logistica'), 'login' => 'nova-logistica', 'email' => 'logistica2@example.test', 'nome' => 'Entregas', 'webhook' => 'https://example.test/eventos'])
            ->assertCreated()->assertJsonPath('data.logistica.nome', 'Entregas');
    }

    public function test_impede_concessao_de_permissoes_alheias_e_confirmacao_manual(): void
    {
        Passport::actingAs($this->administrador(), ['admin:manage']);
        $this->postJson('/api/admin/usuarios', [...$this->payload('administrador'), 'paginas' => ['estoque']])->assertUnprocessable();
        $this->postJson('/api/admin/usuarios', [...$this->payload(), 'email_validado' => true])->assertUnprocessable();
        $this->postJson('/api/admin/usuarios', [...$this->payload(), 'password_confirmation' => 'diferente'])->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['login' => 'nova-conta']);
    }

    public function test_edita_login_senha_e_permissoes_preservando_historico_e_revogando_tokens(): void
    {
        Passport::actingAs($this->administrador(['usuarios', 'estoque']), ['admin:manage']);
        $alvo = $this->administrador(['estoque']);
        $token = $this->tokenDoUsuario($alvo);
        $this->patchJson('/api/admin/usuarios/'.$alvo->id, ['login' => 'login-editado'])->assertOk()->assertJsonPath('data.login', 'login-editado');
        $this->patchJson('/api/admin/usuarios/'.$alvo->id, ['password' => 'V3rde!Azul27', 'password_confirmation' => 'V3rde!Azul27', 'paginas' => ['usuarios']])->assertOk();
        $this->assertDatabaseHas('password_histories', ['usuario_id' => $alvo->id]);
        $this->assertTrue($alvo->refresh()->must_reauthenticate);
        $this->assertSame(['usuarios'], $alvo->administrador->paginas);
        $this->assertDatabaseHas('oauth_access_tokens', ['id' => $token, 'revoked' => true]);
        $this->assertDatabaseHas('oauth_refresh_tokens', ['access_token_id' => $token, 'revoked' => true]);
        $this->patchJson('/api/admin/usuarios/'.$alvo->id, ['password' => 'V3rde!Azul27', 'password_confirmation' => 'V3rde!Azul27'])->assertUnprocessable();
        $this->patchJson('/api/admin/usuarios/'.$alvo->id, ['email' => 'direto@example.test'])->assertUnprocessable();
    }

    public function test_protege_a_propria_conta_e_administradores_com_mais_permissoes(): void
    {
        $admin = $this->administrador();
        $alvo = $this->administrador(['usuarios', 'estoque']);
        Passport::actingAs($admin, ['admin:manage']);
        $this->deleteJson('/api/admin/usuarios/'.$admin->id)->assertForbidden();
        $this->patchJson('/api/admin/usuarios/'.$admin->id, ['paginas' => ['estoque']])->assertUnprocessable();
        $this->patchJson('/api/admin/usuarios/'.$alvo->id, ['login' => 'nao-permitido'])->assertForbidden();
        $this->deleteJson('/api/admin/usuarios/'.$alvo->id)->assertForbidden();
    }

    public function test_troca_email_usa_confirmacao_e_nao_muda_endereco_imediatamente(): void
    {
        $alvo = Usuario::factory()->create();
        $this->mock(EmailVerificationService::class)->shouldReceive('solicitarTroca')->once()
            ->withArgs(fn ($usuario, $email) => $usuario->id === $alvo->id && $email === 'novo@example.test')->andReturnNull();
        Passport::actingAs($this->administrador(), ['admin:manage']);
        $this->postJson('/api/admin/usuarios/'.$alvo->id.'/email', ['email' => 'novo@example.test'])->assertStatus(202);
        $this->assertNotSame('novo@example.test', $alvo->fresh()->email);
    }

    public function test_exclusao_usa_servico_de_exclusao_logica_e_preserva_registros(): void
    {
        $alvo = Usuario::factory()->create();
        $perfil = $alvo->cliente()->create([]);
        $endereco = $alvo->enderecos()->create(['logradouro' => 'Rua Um', 'numero' => '10', 'cidade' => 'São Paulo', 'estado' => 'SP', 'pais' => 'BR', 'cep' => '01001000']);
        Passport::actingAs($this->administrador(), ['admin:manage']);
        $this->deleteJson('/api/admin/usuarios/'.$alvo->id)->assertOk();
        $this->assertSoftDeleted('users', ['id' => $alvo->id]);
        $this->assertSoftDeleted('clientes', ['id' => $perfil->id]);
        $this->assertSoftDeleted('enderecos', ['id' => $endereco->id]);
        $this->getJson('/api/admin/usuarios/'.$alvo->id)->assertNotFound();
    }

    public function test_crud_exige_escopo_perfil_e_permissao_sem_alterar_rotas_do_cliente(): void
    {
        $this->getJson('/api/admin/usuarios')->assertUnauthorized();
        $admin = $this->administrador(['estoque']);
        Passport::actingAs($admin, ['admin:manage']);
        $this->getJson('/api/admin/usuarios')->assertForbidden();
        Passport::actingAs($this->administrador(), ['catalog:read']);
        $this->getJson('/api/admin/usuarios')->assertForbidden();
        $cliente = Usuario::factory()->create(['email_validado' => true]);
        $cliente->cliente()->create([]);
        Passport::actingAs($cliente, ['admin:manage']);
        $this->getJson('/api/admin/usuarios')->assertForbidden();
        $this->patchJson('/api/usuarios/'.$admin->id, ['login' => 'invadido'])->assertForbidden();
        $this->get('/dashboard/usuarios')->assertRedirect('/dashboard/login');
    }

    public function test_proxy_administrativo_funciona_com_permissao_usuarios_sem_estoque(): void
    {
        config(['bff.client_secret' => str_repeat('s', 64)]);
        $this->seed(BffClientSeeder::class);
        $admin = $this->administrador();
        $this->postJson('/dashboard/login', ['login' => $admin->login, 'password' => 'password'])->assertOk();
        $this->get('/dashboard/usuarios')->assertOk();
        $url = $this->postJson('/dashboard/authorize')->assertOk()->json('authorization_url');
        $callback = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->get($callback)->assertRedirect('/dashboard/');
        $this->getJson('/dashboard/api/admin/usuarios')->assertOk()->assertJsonPath('data.total', 1);
        $id = $this->postJson('/dashboard/api/admin/usuarios', $this->payload())->assertCreated()->json('data.id');
        $this->getJson('/dashboard/api/admin/usuarios/'.$id)->assertOk();
        $this->patchJson('/dashboard/api/admin/usuarios/'.$id, ['login' => 'editado-pelo-painel'])->assertOk();
        $this->deleteJson('/dashboard/api/admin/usuarios/'.$id)->assertOk();
        $this->getJson('/dashboard/api/admin/produtos')->assertForbidden();
        $this->assertGuest('web');
    }
}
