<?php

namespace Database\Seeders;

use App\Models\Usuario;
use App\Services\ViaCepService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FreteTesteSeeder extends Seeder
{
    public const DESTINOS = [
        'sao-paulo' => '01001000',
        'rio-de-janeiro' => '20040002',
        'uberlandia' => '38400100',
        'londrina' => '86010010',
        'blumenau' => '89010025',
    ];

    public function run(ViaCepService $viaCep): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Seeder exclusivo de desenvolvimento e testes.');
        $destinos = [];
        foreach (self::DESTINOS as $chave => $cep) {
            $destinos[$chave] = $viaCep->consultar($cep);
        }

        DB::transaction(function () use ($destinos): void {
            $faker = \Faker\Factory::create('pt_BR');
            foreach ($destinos as $chave => $endereco) {
                $login = 'frete-teste-'.$chave;
                $usuario = Usuario::firstOrCreate(['login' => $login], [
                    'email' => $login.'@example.test',
                    'password' => 'FreteDeTeste@2026',
                ]);
                $usuario->forceFill(['email_validado' => true, 'email_verified_at' => now(), 'must_change_password' => false])->save();
                $dados = [
                    'logradouro' => $endereco['logradouro'] ?: $faker->streetName(),
                    'numero' => (string) $faker->numberBetween(1, 999),
                    'complemento' => 'Cadastro fictício para teste de frete',
                    'cidade' => $endereco['localidade'], 'estado' => $endereco['uf'],
                    'pais' => 'BR', 'cep' => self::DESTINOS[$chave],
                ];
                $usuario->enderecos()->firstOrCreate(['cep' => $dados['cep']], $dados);
                $usuario->cliente()->firstOrCreate([], ['dados_fiscais' => [
                    'nome' => $faker->name(), 'email' => $usuario->email,
                    'documento' => $faker->cpf(false), 'indicador_ie' => 9,
                    'endereco' => [...$dados, 'bairro' => $endereco['bairro'], 'codigo_municipio' => $endereco['ibge']],
                ]]);
                $this->command?->info($login.' | '.$dados['cep'].' | '.$dados['cidade'].'/'.$dados['estado']);
            }
        });
    }
}
