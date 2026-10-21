<?php

namespace Tests\Feature\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_oauth_cria_sessao_sem_expor_tokens(): void
    {
        $usuario = Usuario::factory()->create([
            'login' => 'maria', 'email_validado' => true, 'password' => 'senha-segura',
        ]);
        $this->postJson('/oauth/login', ['login' => 'maria', 'password' => 'senha-segura'])
            ->assertOk()->assertJsonStructure(['continue_url'])
            ->assertJsonMissingPath('token')->assertJsonMissingPath('access_token')
            ->assertJsonMissingPath('refresh_token');
        $this->assertAuthenticatedAs($usuario, 'web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rejeita_senha_incorreta(): void
    {
        Usuario::factory()->create(['login' => 'maria', 'email_validado' => true, 'password' => 'senha-segura']);
        $this->postJson('/oauth/login', ['login' => 'maria', 'password' => 'senha-incorreta'])->assertUnauthorized();
        $this->assertGuest('web');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_endpoint_legado_nao_emite_tokens(): void
    {
        $this->postJson('/api/login', ['login' => 'maria', 'password' => 'senha-segura'])->assertNotFound();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
