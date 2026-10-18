<?php

namespace Tests\Feature\Api;

use App\Models\Produto;
use App\Models\NotaEstoque;
use App\Services\CarrinhoService;
use App\Services\ProdutoMovimentacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdutoFiltrosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(CarrinhoService::class)->shouldReceive('comBloqueioDeEstoque')
            ->andReturnUsing(fn ($callback) => $callback());
        $this->mock(ProdutoMovimentacaoService::class)->shouldReceive('contarReservado')->andReturn(0);
        Produto::created(function (Produto $produto): void {
            $produto->movimentacoes()->create([
                'nota_id' => NotaEstoque::create(['referencia' => 'Entrada de teste'])->id,
                'direcao' => 'entrada', 'quantidade' => 10, 'data' => now(),
            ]);
        });
    }

    public function test_filters_are_combined_before_pagination(): void
    {
        foreach (['bandeira', 'camiseta', 'camiseta'] as $indice => $categoria) {
            Produto::create(['codigo' => 'SKU-'.$indice, 'nome' => 'LCB '.$indice, 'categoria' => $categoria, 'ativo' => true]);
        }

        $resposta = $this->getJson('/api/produtos?categoria=camiseta&busca=lcb&per_page=1&page=2');
        $resposta->assertOk()->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.data.0.codigo', 'SKU-2');
    }

    public function test_search_matches_description_and_treats_wildcards_literally(): void
    {
        Produto::create(['codigo' => 'SKU-1', 'nome' => 'Produto', 'descricao' => 'Algodão 100%', 'categoria' => 'camiseta', 'ativo' => true]);
        Produto::create(['codigo' => 'SKU-2', 'nome' => 'Outro produto', 'categoria' => 'outro', 'ativo' => true]);

        $this->getJson('/api/produtos?busca=100%25&per_page=6')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.codigo', 'SKU-1');
        $this->getJson('/api/produtos?busca=sku-2&per_page=6')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.codigo', 'SKU-2');
        $this->getJson('/api/produtos?busca=inexistente&per_page=6')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson('/api/produtos?categoria=invalida&busca[]=teste')->assertUnprocessable();
    }

    public function test_categories_come_from_database_and_products_expose_display_name(): void
    {
        $categoria = \App\Models\Categoria::create(['nome_codigo' => 'adesivo', 'nome' => 'Adesivos']);
        Produto::create(['codigo' => 'ADESIVO-1', 'nome' => 'Adesivo LCB', 'categoria' => 'adesivo', 'ativo' => true]);

        $this->getJson('/api/categorias')->assertOk()->assertJsonFragment([
            'id' => $categoria->id, 'nome_codigo' => 'adesivo', 'nome' => 'Adesivos',
        ]);
        $this->getJson('/api/produtos?categoria=adesivo&per_page=5')->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.categoria', 'adesivo')
            ->assertJsonPath('data.data.0.categoria_cadastro.nome', 'Adesivos');

        $categoria->delete();
        $this->getJson('/api/categorias')->assertOk()->assertJsonMissing(['nome_codigo' => 'adesivo']);
        $this->getJson('/api/produtos?categoria=adesivo')->assertUnprocessable();
        $this->getJson('/api/produtos?busca=ADESIVO-1&per_page=5')->assertOk()
            ->assertJsonPath('data.data.0.categoria_cadastro.nome', 'Adesivos');
    }
}
