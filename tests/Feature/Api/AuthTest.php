<?php

namespace Tests\Feature\Api;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_pode_fazer_login_e_receber_token(): void
    {
        $usuario = Usuario::factory()->create([
            'login' => 'maria',
            'email' => 'maria@example.com',
            'password' => 'senha-segura',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'maria',
            'password' => 'senha-segura',
            'device_name' => 'phpunit',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'token',
                'token_type',
                'usuario' => ['id', 'login', 'email'],
            ])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $usuario->id,
            'name' => 'phpunit',
        ]);
    }

    public function test_login_rejeita_senha_incorreta(): void
    {
        Usuario::factory()->create([
            'login' => 'maria',
            'email' => 'maria@example.com',
            'password' => 'senha-segura',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'maria',
            'password' => 'senha-incorreta',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'As credenciais informadas sao invalidas.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
