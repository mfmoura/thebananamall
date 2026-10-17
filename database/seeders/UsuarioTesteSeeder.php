<?php

namespace Database\Seeders;

use App\Enums\NivelAcesso;
use App\Models\Administrador;
use App\Models\Cliente;
use App\Models\Logistica;
use App\Models\Usuario;
use App\Services\EmailVerificationService;
use Illuminate\Database\Seeder;

class UsuarioTesteSeeder extends Seeder
{
    private const SENHA = 'SenhaDeTeste@2026';

    public function run(EmailVerificationService $emailVerificationService): void
    {
        $cliente = $this->criarUsuario('cliente-teste', 'cliente-teste@example.test');
        Cliente::firstOrCreate(['usuario_id' => $cliente->id]);

        $cliente->enderecos()->firstOrCreate(
            ['logradouro' => 'Rua de Teste', 'numero' => '123'],
            [
                'complemento' => 'Casa',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'pais' => 'BR',
                'cep' => '01001-000',
            ],
        );
        $cliente->enderecos()->firstOrCreate(
            ['logradouro' => 'Avenida de Teste', 'numero' => '456'],
            [
                'complemento' => 'Sala 10',
                'cidade' => 'São Paulo',
                'estado' => 'SP',
                'pais' => 'BR',
                'cep' => '01310-100',
            ],
        );

        $emailAdmin = (string) config('services.initial_admin.email');
        $senhaInicial = (string) (config('services.initial_admin.password') ?: self::SENHA);
        $administrador = Usuario::query()->firstOrNew(['login' => 'admin-teste']);

        if (! $administrador->exists || $administrador->email !== $emailAdmin) {
            $administrador->email = $emailAdmin;
            $administrador->password = $senhaInicial;
            $administrador->email_validado = false;
            $administrador->must_change_password = true;
            $administrador->save();
        }

        Administrador::updateOrCreate(
            ['usuario_id' => $administrador->id],
            ['paginas' => array_map(
                static fn (NivelAcesso $pagina): string => $pagina->value,
                NivelAcesso::cases(),
            )],
        );

        $emailVerificationService->enviarConfirmacaoInicial($administrador);

        $superAdministrador = $this->criarUsuario('superadmin-teste', 'superadmin-teste@example.test');
        Administrador::updateOrCreate(
            ['usuario_id' => $superAdministrador->id],
            ['paginas' => [NivelAcesso::Contabilidade->value]],
        );

        $logistica = $this->criarUsuario('logistica-teste', 'logistica-teste@example.test');
        Logistica::updateOrCreate(
            ['usuario_id' => $logistica->id],
            [
                'nome' => 'Logística de teste',
                'webhook' => 'https://example.test/webhooks/entregas',
            ],
        );
    }

    private function criarUsuario(string $login, string $email): Usuario
    {
        $usuario = Usuario::updateOrCreate(
            ['login' => $login],
            [
                'email' => $email,
                'password' => self::SENHA,
            ],
        );

        $usuario->forceFill([
            'email_validado' => true,
            'email_verified_at' => now(),
            'must_change_password' => false,
        ])->save();

        return $usuario;
    }
}
