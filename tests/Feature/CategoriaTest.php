<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Produto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CategoriaTest extends TestCase
{
    use RefreshDatabase;

    private function entrar(array $paginas = ['estoque'], array $escopos = ['admin:manage']): void
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => $paginas]);
        Passport::actingAs($usuario, $escopos);
    }

    public function test_cadastro_edicao_e_exclusao_preservam_vinculo_do_produto(): void
    {
        $this->entrar();
        $id = $this->postJson('/api/admin/categorias', ['nome_codigo' => 'acessorios', 'nome' => 'Acessórios'])
            ->assertCreated()->json('data.id');
        $produto = Produto::create(['codigo' => 'CAT-1', 'nome' => 'Produto', 'categoria' => 'acessorios']);
        $this->assertEquals($id, $produto->categoriaCadastro->id);
        $url = '/api/admin/categorias/'.$id;
        $this->patchJson($url, ['nome' => 'Acessórios LCB'])->assertOk()->assertJsonPath('data.nome', 'Acessórios LCB');
        $this->patchJson($url, ['nome' => 'Outro', 'nome_codigo' => 'novo'])->assertUnprocessable();
        $this->deleteJson($url)->assertOk();
        $this->assertSoftDeleted('categorias', ['id' => $id]);
        $this->assertEquals('Acessórios LCB', $produto->fresh()->categoriaCadastro->nome);
        $this->getJson('/api/categorias')->assertOk()->assertJsonMissing(['nome_codigo' => 'acessorios']);
        $this->postJson('/api/admin/categorias', ['nome_codigo' => 'acessorios', 'nome' => 'Reutilização'])->assertUnprocessable();
        $this->postJson('/api/admin/produtos', ['codigo' => 'CAT-2', 'nome' => 'Novo', 'categoria' => 'acessorios', 'preco' => 10])->assertUnprocessable();
        $this->patchJson($url, ['nome' => 'Excluída'])->assertNotFound();
    }

    public function test_valida_codigo_e_referencia_no_produto(): void
    {
        $this->entrar();
        foreach (['camiseta', 'Código inválido', str_repeat('a', 41)] as $codigo) {
            $this->postJson('/api/admin/categorias', ['nome_codigo' => $codigo, 'nome' => 'Teste'])->assertUnprocessable();
        }
        $this->postJson('/api/admin/categorias', [])->assertUnprocessable();
        $this->postJson('/api/admin/produtos', ['codigo' => 'CAT-3', 'nome' => 'Novo', 'categoria' => 'inexistente', 'preco' => 10])->assertUnprocessable();
    }

    public function test_escritas_exigem_permissao_estoque_e_escopo_oauth(): void
    {
        $categoria = Categoria::firstOrFail();
        $this->postJson('/api/admin/categorias', [])->assertUnauthorized();
        foreach ([[['contabilidade'], ['admin:manage']], [['estoque'], []]] as [$paginas, $escopos]) {
            $this->entrar($paginas, $escopos);
            $this->postJson('/api/admin/categorias', [])->assertForbidden();
            $this->patchJson('/api/admin/categorias/'.$categoria->id, ['nome' => 'Alteração'])->assertForbidden();
            $this->deleteJson('/api/admin/categorias/'.$categoria->id)->assertForbidden();
        }
    }
}
