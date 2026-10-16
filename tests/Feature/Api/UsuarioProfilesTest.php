<?php

namespace Tests\Feature\Api;

use App\Enums\NivelAcesso;
use App\Models\Administrador;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioProfilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_creates_customer_and_optional_address(): void
    {
        $response = $this->postJson('/api/usuarios', [
            'login' => 'cliente',
            'email' => 'cliente@example.com',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'endereco' => [
                'logradouro' => 'Rua Um',
                'numero' => '10',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'pais' => 'BR',
                'cep' => '01001-000',
            ],
        ]);

        $usuarioId = $response->json('id');

        $response->assertCreated()
            ->assertJsonPath('cliente.usuario_id', $usuarioId)
            ->assertJsonPath('enderecos.0.cidade', 'São Paulo');

        $this->assertDatabaseHas('clientes', ['usuario_id' => $usuarioId]);
        $this->assertDatabaseHas('enderecos', ['usuario_id' => $usuarioId, 'cep' => '01001-000']);
    }

    public function test_public_registration_cannot_create_an_administrator(): void
    {
        $this->postJson('/api/usuarios', [
            'login' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'tipo' => 'administrador',
            'perfil' => ['nivel_de_acesso' => NivelAcesso::Administrador->value],
        ])->assertForbidden();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_administrator_can_create_logistics_user(): void
    {
        $administrador = Usuario::factory()->create();
        Administrador::create([
            'usuario_id' => $administrador->id,
            'nivel_de_acesso' => NivelAcesso::Administrador,
        ]);

        Sanctum::actingAs($administrador);

        $this->postJson('/api/admin/usuarios', [
            'login' => 'webhook-pagamentos',
            'email' => 'webhook@example.com',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'tipo' => 'logistica',
            'perfil' => [
                'nome' => 'Webhook de pagamentos',
                'webhook' => 'https://example.com/webhooks/payments',
            ],
        ])->assertCreated()
            ->assertJsonPath('logistica.nome', 'Webhook de pagamentos');

        $this->assertDatabaseHas('logisticas', [
            'nome' => 'Webhook de pagamentos',
            'webhook' => 'https://example.com/webhooks/payments',
        ]);
    }

    public function test_administrator_can_create_another_administrator(): void
    {
        $administrador = $this->createAdministrator(NivelAcesso::Administrador);
        Sanctum::actingAs($administrador);

        $this->postJson('/api/admin/usuarios', [
            'login' => 'novo-admin',
            'email' => 'novo-admin@example.com',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'tipo' => 'administrador',
            'perfil' => ['nivel_de_acesso' => NivelAcesso::Administrador->value],
        ])->assertCreated()
            ->assertJsonPath('administrador.nivel_de_acesso', NivelAcesso::Administrador->value);

        $this->assertDatabaseCount('administradores', 2);
    }

    public function test_regular_administrator_cannot_create_super_administrator(): void
    {
        Sanctum::actingAs($this->createAdministrator(NivelAcesso::Administrador));

        $this->postJson('/api/admin/usuarios', [
            'login' => 'super-admin',
            'email' => 'super-admin@example.com',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'tipo' => 'administrador',
            'perfil' => ['nivel_de_acesso' => NivelAcesso::SuperAdministrador->value],
        ])->assertForbidden();

        $this->assertDatabaseCount('administradores', 1);
    }

    public function test_super_administrator_can_create_super_administrator(): void
    {
        Sanctum::actingAs($this->createAdministrator(NivelAcesso::SuperAdministrador));

        $this->postJson('/api/admin/usuarios', [
            'login' => 'super-admin-novo',
            'email' => 'super-admin-novo@example.com',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
            'tipo' => 'administrador',
            'perfil' => ['nivel_de_acesso' => NivelAcesso::SuperAdministrador->value],
        ])->assertCreated()
            ->assertJsonPath('administrador.nivel_de_acesso', NivelAcesso::SuperAdministrador->value);
    }

    public function test_customer_can_have_multiple_addresses(): void
    {
        $response = $this->postJson('/api/usuarios', [
            'login' => 'cliente-enderecos',
            'email' => 'enderecos@example.com',
            'password' => 'senha-segura',
            'password_confirmation' => 'senha-segura',
        ])->assertCreated();

        $usuario = Usuario::findOrFail($response->json('id'));
        $usuario->enderecos()->create([
            'logradouro' => 'Rua Dois',
            'numero' => '20',
            'cidade' => 'Campinas',
            'estado' => 'SP',
            'pais' => 'BR',
            'cep' => '13010-000',
        ]);
        $usuario->enderecos()->create([
            'logradouro' => 'Avenida Três',
            'numero' => '30',
            'cidade' => 'Campinas',
            'estado' => 'SP',
            'pais' => 'BR',
            'cep' => '13020-000',
        ]);

        $this->assertCount(2, $usuario->enderecos);
        $this->assertCount(1, $usuario->cliente()->get());
    }

    private function createAdministrator(NivelAcesso $nivel): Usuario
    {
        $usuario = Usuario::factory()->create();
        Administrador::create([
            'usuario_id' => $usuario->id,
            'nivel_de_acesso' => $nivel,
        ]);

        return $usuario;
    }
}
