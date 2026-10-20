<?php

namespace Tests\Feature;

use App\Models\NotaEstoque;
use App\Models\Produto;
use App\Services\ReservaEstoqueService;
use Database\Seeders\ProdutoGradeTesteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProdutoGradeTesteSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_distribui_saldo_preserva_historico_e_nao_duplica_ao_reexecutar(): void
    {
        Redis::shouldReceive('hgetall')->andReturn([]);
        $produto = Produto::create(['codigo' => 'LCB-DEMO-MOLETOM', 'nome' => 'Moletom', 'categoria' => 'outro']);
        $outro = Produto::create(['codigo' => 'FORA-DA-DEMO', 'nome' => 'Outro', 'categoria' => 'camiseta']);
        $original = $produto->movimentacoes()->create(['nota_id' => NotaEstoque::create(['referencia' => 'TESTE'])->id,
            'direcao' => 'entrada', 'quantidade' => 30, 'data' => now()]);
        $this->seed(ProdutoGradeTesteSeeder::class);
        $this->seed(ProdutoGradeTesteSeeder::class);
        $this->assertSame(['P', 'M', 'G', 'GG'], $produto->refresh()->grade);
        $this->assertNull($outro->refresh()->grade);
        $this->assertNull($original->refresh()->tamanho);
        $this->assertSame('30.000', $original->quantidade);
        $this->assertDatabaseCount('produto_movimentacoes', 6);
        $this->assertDatabaseCount('notas_estoque', 2);
        $this->assertDatabaseHas('produto_movimentacoes', ['estorno_de_id' => $original->id, 'direcao' => 'saida', 'quantidade' => 30]);
        $reservas = app(ReservaEstoqueService::class);
        $this->assertSame(30.0, $reservas->contarEstoque($produto));
        foreach (['P' => 8.0, 'M' => 8.0, 'G' => 7.0, 'GG' => 7.0] as $tamanho => $saldo) {
            $this->assertSame($saldo, $reservas->contarEstoque($produto, $tamanho));
        }
    }

    public function test_reserva_ativa_impede_conversao_sem_alterar_o_produto(): void
    {
        $produto = Produto::create(['codigo' => 'LCB-CAMISETA-001', 'nome' => 'Camiseta', 'categoria' => 'camiseta']);
        $this->mock(ReservaEstoqueService::class)->shouldReceive('contarReservado')->with($produto->id)->andReturn(1.0);
        try {
            $this->seed(ProdutoGradeTesteSeeder::class);
            $this->fail('Deveria bloquear a conversão com reserva ativa.');
        } catch (ValidationException) {
            $this->assertNull($produto->refresh()->grade);
            $this->assertDatabaseCount('produto_movimentacoes', 0);
        }
    }
}
