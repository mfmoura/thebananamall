<?php

namespace Database\Seeders;

use App\Enums\NivelAcesso;
use App\Models\Administrador;
use App\Models\Cliente;
use App\Models\Logistica;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class UsuarioTesteSeeder extends Seeder
{
    private const SENHA = 'Senha@12345';

    public function run(): void
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

        $administrador = $this->criarUsuario('admin-teste', 'admin-teste@example.test');
        Administrador::updateOrCreate(
            ['usuario_id' => $administrador->id],
            ['nivel_de_acesso' => NivelAcesso::Administrador],
        );

        $superAdministrador = $this->criarUsuario('superadmin-teste', 'superadmin-teste@example.test');
        Administrador::updateOrCreate(
            ['usuario_id' => $superAdministrador->id],
            ['nivel_de_acesso' => NivelAcesso::SuperAdministrador],
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
        return Usuario::updateOrCreate(
            ['login' => $login],
            [
                'email' => $email,
                'password' => self::SENHA,
            ],
        );
    }
}
