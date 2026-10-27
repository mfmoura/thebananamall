<?php

namespace Database\Seeders;

use App\Models\Produto;
use Illuminate\Database\Seeder;

class ProdutoDescricaoSeeder extends Seeder
{
    public function run(): void
    {
        Produto::query()->where(function ($query): void {
            $query->whereNull('descricao')->orWhere('descricao', '');
        })->chunkById(100, function ($produtos): void {
            foreach ($produtos as $produto) {
                $produto->update(['descricao' => $this->descricao($produto)]);
            }
        });
    }

    private function descricao(Produto $produto): string
    {
        $textos = [
            'LCB-BANDEIRA-001' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-BOTTON-001' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-CAMISETA-001' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-CAMISETA-VERMELHA' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-CAMISETA-PRETA' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-CAMISETA-BRANCA' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-REGATA' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-MOLETOM' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-CROPPED' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-BOTTON-ESTRELA' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-BOTTON-UNIDADE' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-BOTTON-SOLIDARIEDADE' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-BOTTON-TRABALHADORES' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-BOTTON-PODER' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
            'LCB-DEMO-BOTTON-LUTA' => 'Produto de demonstração. Personalize a descrição para apresentar sua coleção.',
        ];

        return $textos[$produto->codigo] ?? "{$produto->nome}. Produto da nossa coleção.";
    }
}
