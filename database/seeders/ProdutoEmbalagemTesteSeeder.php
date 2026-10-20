<?php

namespace Database\Seeders;

use App\Models\Produto;
use Illuminate\Database\Seeder;

class ProdutoEmbalagemTesteSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        $codigos = ['LCB-BANDEIRA-001', 'LCB-BOTTON-001', 'LCB-CAMISETA-001',
            'LCB-DEMO-CAMISETA-VERMELHA', 'LCB-DEMO-CAMISETA-PRETA', 'LCB-DEMO-CAMISETA-BRANCA',
            'LCB-DEMO-REGATA', 'LCB-DEMO-MOLETOM', 'LCB-DEMO-CROPPED', 'LCB-DEMO-BOTTON-ESTRELA', 'LCB-DEMO-BOTTON-UNIDADE',
            'LCB-DEMO-BOTTON-SOLIDARIEDADE', 'LCB-DEMO-BOTTON-TRABALHADORES', 'LCB-DEMO-BOTTON-PODER', 'LCB-DEMO-BOTTON-LUTA'];
        foreach (Produto::whereIn('codigo', $codigos)->get() as $produto) {
            if ($produto->peso_gramas !== null) continue;
            [$peso, $altura, $largura, $comprimento] = match (true) {
                str_contains($produto->codigo, 'MOLETOM') => [800, 10, 28, 35],
                str_contains($produto->codigo, 'BOTTON') => [30, 2, 8, 8],
                str_contains($produto->codigo, 'BANDEIRA') => [250, 3, 20, 25],
                str_contains($produto->codigo, 'REGATA'), str_contains($produto->codigo, 'CROPPED') => [200, 3, 20, 25],
                default => [300, 4, 25, 30],
            };
            $produto->update(['peso_gramas' => $peso, 'altura_cm' => $altura, 'largura_cm' => $largura,
                'comprimento_cm' => $comprimento, 'embalagem_estimada' => true]);
            $this->command?->info($produto->codigo.' | '.$peso.' g | '.$altura.' x '.$largura.' x '.$comprimento.' cm (estimativa)');
        }
    }
}
