<?php

namespace Tests\Feature\Api;

use App\Models\Usuario;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class UsuarioProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(EmailVerificationService::class)->shouldReceive('enviarConfirmacaoInicial')->andReturnNull();
        Http::preventStrayRequests();
    }

    private function payload(): array
    {
        return ['login' => 'nova-conta', 'email' => 'nova@example.test',
            'password' => 'V3rde!Azul27', 'password_confirmation' => 'V3rde!Azul27'];
    }

    private function administrador(array $paginas): Usuario
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => $paginas]);
        Passport::actingAs($usuario, ['admin:manage']);
        return $usuario;
    }

    public function test_cadastro_publico_cria_cliente_com_endereco_confirmado(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response(['cep' => '01001-000', 'uf' => 'SP', 'localidade' => 'São Paulo'])]);
        $response = $this->postJson('/api/usuarios', $this->payload() + ['endereco' => [
            'logradouro' => 'Rua Um', 'numero' => '10', 'cidade' => 'São Paulo',
            'estado' => 'SP', 'pais' => 'BR', 'cep' => '01001-000',
        ]])->assertCreated()->assertJsonPath('data.enderecos.0.cidade', 'São Paulo');
        $id = $response->json('data.id');
        $response->assertJsonPath('data.cliente.usuario_id', $id);
        $this->assertDatabaseHas('enderecos', ['usuario_id' => $id, 'cep' => '01001-000']);
    }

    public function test_cadastro_publico_nao_concede_perfil_administrativo(): void
    {
        $this->postJson('/api/usuarios', $this->payload() + ['tipo' => 'administrador', 'perfil' => ['paginas' => ['usuarios']]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_manutencao_cria_logistica_com_o_contrato_atual(): void
    {
        $this->administrador(['manutencao']);
        $this->postJson('/api/admin/usuarios', $this->payload() + [
            'tipo' => 'logistica', 'nome' => 'Webhook', 'webhook' => 'https://example.test/webhook',
        ])->assertCreated()->assertJsonPath('data.logistica.nome', 'Webhook');
        $this->assertDatabaseHas('logisticas', ['nome' => 'Webhook', 'webhook' => 'https://example.test/webhook']);
    }

    public function test_gestao_de_usuarios_cria_administrador_com_suas_permissoes(): void
    {
        $this->administrador(['usuarios']);
        $this->postJson('/api/admin/usuarios', $this->payload() + ['tipo' => 'administrador', 'paginas' => ['usuarios']])
            ->assertCreated()->assertJsonPath('data.administrador.paginas', ['usuarios']);
        $this->assertDatabaseCount('administradores', 2);
    }

    public function test_gestor_nao_concede_permissao_que_nao_possui(): void
    {
        $this->administrador(['usuarios']);
        $this->postJson('/api/admin/usuarios', $this->payload() + ['tipo' => 'administrador', 'paginas' => ['manutencao']])
            ->assertUnprocessable();
        $this->assertDatabaseCount('administradores', 1);
    }

    public function test_manutencao_pode_conceder_paginas_administrativas(): void
    {
        $this->administrador(['manutencao']);
        $this->postJson('/api/admin/usuarios', $this->payload() + ['tipo' => 'administrador', 'paginas' => ['usuarios', 'pedidos']])
            ->assertCreated()->assertJsonPath('data.administrador.paginas', ['usuarios', 'pedidos']);
    }

    public function test_cliente_pode_ter_multiplos_enderecos(): void
    {
        $response = $this->postJson('/api/usuarios', $this->payload())->assertCreated();
        $usuario = Usuario::findOrFail($response->json('data.id'));
        foreach (['20', '30'] as $numero) {
            $usuario->enderecos()->create(['logradouro' => 'Rua Dois', 'numero' => $numero,
                'cidade' => 'Campinas', 'estado' => 'SP', 'pais' => 'BR', 'cep' => '13010-000']);
        }
        $this->assertCount(2, $usuario->enderecos);
        $this->assertCount(1, $usuario->cliente()->get());
    }
}
