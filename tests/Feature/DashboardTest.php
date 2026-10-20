<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): Usuario
    {
        $usuario = Usuario::factory()->create(['email_validado' => true, 'password' => 'senha-segura']);
        $usuario->administrador()->create(['paginas' => []]);

        return $usuario;
    }

    public function test_painel_exige_login_proprio_e_nao_oferece_cadastro(): void
    {
        $this->get('/dashboard/')->assertRedirect('/dashboard/login');
        $this->get('/dashboard/login')->assertOk();
        $this->get('/dashboard/cadastro')->assertNotFound();
        $this->actingAs($this->administrador(), 'web');
        $this->get('/dashboard/')->assertRedirect('/dashboard/login');
        $this->getJson('/dashboard/session')->assertUnauthorized();
    }

    public function test_login_administrativo_e_logout(): void
    {
        $usuario = $this->administrador();
        $this->postJson('/dashboard/login', ['login' => $usuario->email, 'password' => 'senha-segura'])->assertOk();
        $this->assertAuthenticatedAs($usuario, 'dashboard');
        $this->assertGuest('web');
        $this->get('/dashboard/')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/dashboard/session')->assertExactJson(['id' => $usuario->id, 'login' => $usuario->login, 'paginas' => [], 'api_autorizada' => false]);
        $this->postJson('/dashboard/logout')->assertOk();
        $this->assertGuest('dashboard');
        $this->getJson('/dashboard/session')->assertUnauthorized();
    }

    public function test_rejeita_cliente_senha_errada_e_conta_bloqueada(): void
    {
        $this->mock(\App\Services\EmailVerificationService::class)->shouldReceive('enviarConfirmacaoInicial')->once();
        $usuario = Usuario::factory()->create(['email_validado' => true, 'password' => 'senha-segura']);
        $this->postJson('/dashboard/login', ['login' => $usuario->login, 'password' => 'senha-segura'])->assertForbidden();
        $this->assertGuest('dashboard');
        $usuario->administrador()->create(['paginas' => []]);
        $this->postJson('/dashboard/login', ['login' => $usuario->login, 'password' => 'errada'])->assertUnauthorized();
        $usuario->forceFill(['must_change_password' => true])->save();
        $this->postJson('/dashboard/login', ['login' => $usuario->login, 'password' => 'senha-segura'])->assertForbidden();
        $usuario->forceFill(['must_change_password' => false, 'email_validado' => false])->save();
        $this->postJson('/dashboard/login', ['login' => $usuario->login, 'password' => 'senha-segura'])->assertForbidden();
    }

    public function test_revogar_perfil_bloqueia_sessao_existente(): void
    {
        $usuario = $this->administrador();
        $this->actingAs($usuario, 'dashboard');
        $usuario->administrador()->first()->delete();
        $this->getJson('/dashboard/session')->assertUnauthorized();
        $this->get('/dashboard/')->assertRedirect('/dashboard/login');
    }

    public function test_logout_administrativo_preserva_login_cliente(): void
    {
        $cliente = Usuario::factory()->create(['email_validado' => true]);
        $this->actingAs($cliente, 'web')->actingAs($this->administrador(), 'dashboard');
        $this->postJson('/dashboard/logout')->assertOk();
        $this->assertAuthenticatedAs($cliente, 'web');
        $this->assertGuest('dashboard');
    }

    public function test_login_pendente_solicita_confirmacao_uma_vez_por_minuto_sem_autenticar(): void
    {
        $usuario = $this->administrador();
        $usuario->forceFill(['email_validado' => false, 'email_confirmation_expires_at' => now()->subDay()])->save();
        $this->mock(\App\Services\EmailVerificationService::class)->shouldReceive('enviarConfirmacaoInicial')
            ->twice()->with(\Mockery::on(fn ($conta) => $conta->id === $usuario->id));
        $dados = ['login' => $usuario->login, 'password' => 'senha-segura'];
        $this->postJson('/dashboard/login', $dados)->assertForbidden()->assertJsonPath('message', 'Confirme seu e-mail antes de entrar. A solicitação de envio foi aceita; confira sua caixa de entrada e spam. Aguarde um minuto antes de solicitar outro envio pelo login.');
        $this->postJson('/dashboard/login', $dados)->assertForbidden();
        $this->assertGuest('dashboard');
        $this->assertNull($usuario->fresh()->last_login_at);
        $this->travel(61)->seconds();
        $this->postJson('/dashboard/login', $dados)->assertForbidden();
    }

    public function test_credenciais_invalidas_ou_cliente_nao_disparam_confirmacao(): void
    {
        $this->mock(\App\Services\EmailVerificationService::class)->shouldNotReceive('enviarConfirmacaoInicial');
        $usuario = $this->administrador();
        $usuario->forceFill(['email_validado' => false])->save();
        $this->postJson('/dashboard/login', ['login' => $usuario->login, 'password' => 'errada'])->assertUnauthorized();
        $cliente = Usuario::factory()->create(['email_validado' => false, 'password' => 'senha-segura']);
        $this->postJson('/dashboard/login', ['login' => $cliente->login, 'password' => 'senha-segura'])->assertForbidden();
    }
}
