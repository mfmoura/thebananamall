<?php

namespace Database\Seeders;

use App\Models\NotaEstoque;
use App\Models\Produto;
use App\Services\CarrinhoService;
use App\Services\ReservaEstoqueService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProdutoGradeTesteSeeder extends Seeder
{
    public const CODIGOS = [
        'LCB-CAMISETA-001', 'LCB-DEMO-CAMISETA-VERMELHA', 'LCB-DEMO-CAMISETA-PRETA',
        'LCB-DEMO-CAMISETA-BRANCA', 'LCB-DEMO-REGATA', 'LCB-DEMO-MOLETOM', 'LCB-DEMO-CROPPED',
    ];

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        $reservas = app(ReservaEstoqueService::class);
        app(CarrinhoService::class)->comBloqueioDeEstoque(fn () => DB::transaction(function () use ($reservas): void {
            $produtos = Produto::whereIn('codigo', self::CODIGOS)->orderBy('id')->lockForUpdate()->get();
            foreach ($produtos as $produto) {
                if (! empty($produto->grade)) continue;

                // Exceção restrita aos dados de demonstração ainda sem histórico comercial.
                if ($produto->itensPedido()->withTrashed()->exists()
                    || $produto->itensNotaFiscal()->withTrashed()->exists()
                    || $reservas->contarReservado($produto->id) > 0) {
                    throw ValidationException::withMessages(['grade' => $produto->codigo.': possui pedidos, notas fiscais ou reservas; a conversão de demonstração não é permitida.']);
                }
                $saldo = $reservas->contarEstoque($produto);
                $movimentos = $produto->movimentacoes()->withTrashed()->get();
                $entrada = $movimentos->first();
                if ($saldo < 0 || floor($saldo) !== $saldo
                    || ($movimentos->isNotEmpty() && ($movimentos->count() !== 1
                        || $entrada->direcao->value !== 'entrada' || $entrada->tamanho !== null
                        || $entrada->estorno_de_id !== null))) {
                    throw ValidationException::withMessages(['grade' => $produto->codigo.': estoque incompatível com a grade de demonstração.']);
                }

                $grade = ['P', 'M', 'G', 'GG'];
                if ($saldo > 0) {
                    $nota = NotaEstoque::create(['referencia' => 'SEED-GRADE-TESTE-'.$produto->id,
                        'status' => 'Ajuste de Estoque',
                        'observacao' => 'Distribuição do saldo de demonstração entre tamanhos, preservando as movimentações sem tamanho.']);
                    $produto->movimentacoes()->create(['nota_id' => $nota->id, 'direcao' => 'saida',
                        'quantidade' => $saldo, 'tamanho' => null, 'data' => now(), 'estorno_de_id' => $entrada->id]);
                    foreach ($grade as $indice => $tamanho) {
                        $quantidade = intdiv((int) $saldo, count($grade)) + ($indice < (int) $saldo % count($grade) ? 1 : 0);
                        if ($quantidade === 0) continue;
                        $produto->movimentacoes()->create(['nota_id' => $nota->id, 'direcao' => 'entrada',
                            'quantidade' => $quantidade, 'tamanho' => $tamanho, 'data' => now()]);
                    }
                }
                $produto->update(['grade' => $grade]);
                $this->command?->info($produto->codigo.' | P, M, G, GG | saldo total preservado: '.$saldo);
            }
        }));
    }
}
