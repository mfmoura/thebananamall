<?php

namespace Database\Seeders;

use App\Models\FormaPagamento;
use Illuminate\Database\Seeder;

class FormaPagamentoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['codigo' => 'pix', 'nome' => 'PIX'],
            ['codigo' => 'cartao_credito', 'nome' => 'Cartão de crédito'],
            ['codigo' => 'cartao_debito', 'nome' => 'Cartão de débito'],
            ['codigo' => 'boleto', 'nome' => 'Boleto'],
        ] as $forma) {
            FormaPagamento::query()->updateOrCreate(
                ['codigo' => $forma['codigo']],
                ['nome' => $forma['nome'], 'ativo' => true],
            );
        }
    }
}
