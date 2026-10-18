<?php

namespace Database\Seeders;

use App\Enums\CategoriaProduto;
use App\Enums\DirecaoMovimentacao;
use App\Models\NotaEstoque;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use Illuminate\Database\Seeder;

class ProdutoTesteSeeder extends Seeder
{
    public function run(): void
    {
        $produtos = [
            [
                'codigo' => 'LCB-BANDEIRA-001',
                'nome' => 'Bandeira da Liga Comunista Brasileira',
                'categoria' => CategoriaProduto::Bandeira,
                'preco' => '35.00',
                'nome_legado' => 'Produto de teste 1',
            ],
            [
                'codigo' => 'LCB-BOTTON-001',
                'nome' => 'Botton LCB — A luta continua',
                'categoria' => CategoriaProduto::Botton,
                'preco' => '8.50',
                'nome_legado' => 'Produto de teste 2',
            ],
            [
                'codigo' => 'LCB-CAMISETA-001',
                'nome' => 'Camiseta LCB — Trabalhadores de todo o mundo, uni-vos',
                'categoria' => CategoriaProduto::Camiseta,
                'preco' => '49.90',
                'nome_legado' => null,
            ],
        ];

        $nota = NotaEstoque::query()->firstOrCreate(
            ['referencia' => 'SEED-ESTOQUE-DEMO'],
            ['observacao' => 'Estoque inicial para desenvolvimento local.'],
        );

        foreach ($produtos as $dados) {
            $produto = Produto::query()->where('codigo', $dados['codigo'])->first();

            if ($produto === null && $dados['nome_legado'] !== null) {
                $produto = Produto::query()->where('nome', $dados['nome_legado'])->first();
            }

            $produto ??= new Produto();
            $produto->fill([
                'codigo' => $dados['codigo'],
                'nome' => $dados['nome'],
                'categoria' => $dados['categoria']->value,
                'ativo' => true,
            ]);
            $produto->save();

            if (! $produto->precos()->exists()) {
                $produto->precos()->create(['preco' => $dados['preco'], 'data' => now()]);
            }

            ProdutoMovimentacao::query()->firstOrCreate(
                ['nota_id' => $nota->id, 'produto_id' => $produto->id],
                [
                    'direcao' => DirecaoMovimentacao::Entrada,
                    'quantidade' => 100,
                    'data' => now(),
                ],
            );
        }
    }
}
