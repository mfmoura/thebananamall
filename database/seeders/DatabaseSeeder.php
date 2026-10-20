<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OAuthClientSeeder::class,
            UsuarioTesteSeeder::class,
            FormaPagamentoSeeder::class,
            ProdutoTesteSeeder::class,
            ProdutoCatalogoSeeder::class,
            ProdutoDescricaoSeeder::class,
        ]);
        if (app()->environment(['local', 'testing'])) {
            $this->call(ProdutoEmbalagemTesteSeeder::class);
            $this->call(ProdutoGradeTesteSeeder::class);
        }
        if (config('bff.client_secret')) {
            $this->call(BffClientSeeder::class);
        }
    }
}
