<?php

namespace Database\Seeders;

use App\Enums\DirecaoMovimentacao;
use App\Models\NotaEstoque;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Services\ProdutoImagemService;
use Illuminate\Database\Seeder;

class ProdutoCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $itens = [
            ['CAMISETA-VERMELHA', 'Camiseta LCB vermelha', 'camiseta', '49.90', 'camiseta-vermelha'],
            ['CAMISETA-PRETA', 'Camiseta LCB preta', 'camiseta', '49.90', 'camiseta-preta'],
            ['CAMISETA-BRANCA', 'Camiseta LCB branca', 'camiseta', '49.90', 'camiseta-branca'],
            ['REGATA', 'Regata LCB — Organização e luta', 'outro', '39.90', 'regata'],
            ['MOLETOM', 'Moletom LCB — Solidariedade', 'outro', '119.90', 'moletom'],
            ['CROPPED', 'Cropped LCB — Poder popular', 'outro', '44.90', 'cropped'],
            ['BOTTON-ESTRELA', 'Botton LCB — Estrela vermelha', 'botton', '8.50', 'botton-estrela'],
            ['BOTTON-UNIDADE', 'Botton LCB — Unidade', 'botton', '8.50', 'botton-unidade'],
            ['BOTTON-SOLIDARIEDADE', 'Botton LCB — Solidariedade', 'botton', '8.50', 'botton-solidariedade'],
            ['BOTTON-TRABALHADORES', 'Botton LCB — Trabalhadores', 'botton', '8.50', 'botton-trabalhadores'],
            ['BOTTON-PODER', 'Botton LCB — Poder popular', 'botton', '8.50', 'botton-poder'],
            ['BOTTON-LUTA', 'Botton LCB — A luta continua', 'botton', '8.50', 'botton-luta'],
        ];
        $nota = NotaEstoque::query()->firstOrCreate(['referencia' => 'SEED-CATALOGO-REACT'], ['observacao' => 'Produtos e estoque de demonstração do catálogo.']);
        $imagens = app(ProdutoImagemService::class);

        foreach ($itens as [$codigo, $nome, $categoria, $preco, $arquivo]) {
            $produto = Produto::query()->firstOrCreate(['codigo' => "LCB-DEMO-{$codigo}"], ['nome' => $nome, 'categoria' => $categoria, 'ativo' => true]);
            if (! $produto->precos()->exists()) {
                $produto->precos()->create(['preco' => $preco, 'data' => now()]);
            }
            ProdutoMovimentacao::query()->firstOrCreate(['nota_id' => $nota->id, 'produto_id' => $produto->id], ['direcao' => DirecaoMovimentacao::Entrada, 'quantidade' => 30, 'data' => now()]);
            foreach (['frente', 'verso', 'detalhe'] as $vista) {
                $path = rtrim(config('app.url'), '/')."/images/produtos/{$arquivo}-{$vista}.svg";
                if (! $produto->imagens()->where('path', $path)->exists()) {
                    $imagens->criar($produto, ['path' => $path]);
                }
            }
        }
    }
}
