<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Services\CarrinhoService;
use App\Services\UsuarioExclusaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministradorPrincipalExpiracaoTest extends TestCase
{
    use RefreshDatabase;

    private function pendente(string $login): Usuario
    {
        return Usuario::factory()->create([
            'login' => $login,
            'email_validado' => false,
            'last_login_at' => null,
            'email_confirmation_expires_at' => now()->subMinute(),
        ]);
    }

    private function servico(): UsuarioExclusaoService
    {
        $this->mock(CarrinhoService::class)->shouldReceive('comBloqueioDeEstoque')
            ->andReturnUsing(fn ($acao) => $acao());

        return app(UsuarioExclusaoService::class);
    }

    public function test_limpeza_preserva_administradores_e_exclui_conta_comum_expirada(): void
    {
        $principal = $this->pendente('admin-teste');
        $principal->administrador()->create(['paginas' => ['usuarios']]);
        $outro = $this->pendente('outro-admin');
        $outro->administrador()->create(['paginas' => ['usuarios']]);
        $comum = $this->pendente('conta-comum');

        $this->assertSame(1, $this->servico()->limparExpirados());
        $this->assertNotSoftDeleted('users', ['id' => $principal->id]);
        $this->assertNotSoftDeleted('administradores', ['usuario_id' => $principal->id]);
        $this->assertNotSoftDeleted('users', ['id' => $outro->id]);
        $this->assertNotSoftDeleted('administradores', ['usuario_id' => $outro->id]);
        $this->assertSoftDeleted('users', ['id' => $comum->id]);
        $this->assertFalse($principal->fresh()->email_validado);
    }

    public function test_revalidacao_sob_bloqueio_impede_exclusao_automatica_do_principal(): void
    {
        $principal = $this->pendente('admin-teste');
        $this->assertFalse($this->servico()->excluir($principal, somenteExpirado: true));
        $this->assertNotSoftDeleted('users', ['id' => $principal->id]);
    }

    public function test_excecao_nao_impede_exclusao_explicita_do_principal(): void
    {
        $principal = $this->pendente('admin-teste');
        $this->assertTrue($this->servico()->excluir($principal));
        $this->assertSoftDeleted('users', ['id' => $principal->id]);
    }

    public function test_outro_administrador_tambem_e_protegido_sob_bloqueio(): void
    {
        $usuario = $this->pendente('outro-admin');
        $usuario->administrador()->create(['paginas' => ['estoque']]);
        $this->assertFalse($this->servico()->excluir($usuario, somenteExpirado: true));
        $this->assertNotSoftDeleted('users', ['id' => $usuario->id]);
    }
}
