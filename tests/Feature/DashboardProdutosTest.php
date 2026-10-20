<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\Usuario;
use Database\Seeders\BffClientSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DashboardProdutosTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(array $paginas = ['estoque']): Usuario
    {
        $usuario = Usuario::factory()->create(['email_validado' => true, 'password' => 'senha-segura']);
        $usuario->administrador()->create(['paginas' => $paginas]);

        return $usuario;
    }

    private function produto(bool $ativo = true): Produto
    {
        return Produto::create(['codigo' => fake()->unique()->bothify('SKU-####??'), 'nome' => 'Produto teste', 'categoria' => 'camiseta', 'ativo' => $ativo]);
    }

    public function test_cadastra_peso_medidas_e_marcacao_de_estimativa(): void
    {
        $produto = $this->produto();
        Passport::actingAs($this->administrador(), ['admin:manage']);
        $url = '/api/admin/produtos/'.$produto->id;
        $this->patchJson($url, ['peso_gramas' => 300])->assertUnprocessable();
        $this->patchJson($url, ['peso_gramas' => 300, 'altura_cm' => 4, 'largura_cm' => 25,
            'comprimento_cm' => 30, 'embalagem_estimada' => true])->assertOk()
            ->assertJsonPath('data.peso_gramas', 300)->assertJsonPath('data.altura_cm', '4.00')
            ->assertJsonPath('data.embalagem_estimada', true);
        $this->patchJson($url, ['peso_gramas' => -1])->assertUnprocessable();
    }

    public function test_listagem_administrativa_paginada_inclui_inativos_sem_estoque(): void
    {
        $ativo = $this->produto();
        $inativo = $this->produto(false);
        $excluido = $this->produto();
        $excluido->delete();
        Passport::actingAs($this->administrador(), ['admin:manage']);

        $this->getJson('/api/admin/produtos?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.total', 2)->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.data.0.id', $inativo->id);
        $this->getJson('/api/admin/produtos/'.$inativo->id)->assertOk()->assertJsonPath('data.ativo', false);
        $this->getJson('/api/admin/produtos?busca='.$ativo->codigo)->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/admin/produtos?per_page=100')->assertUnprocessable();
    }

    public function test_catalogo_administrativo_exige_perfil_pagina_e_escopo(): void
    {
        $this->getJson('/api/admin/produtos')->assertUnauthorized();
        Passport::actingAs($this->administrador(['contabilidade']), ['admin:manage']);
        $this->getJson('/api/admin/produtos')->assertForbidden();
        Passport::actingAs($this->administrador(), ['catalog:read']);
        $this->getJson('/api/admin/produtos')->assertForbidden();
    }

    public function test_dashboard_so_libera_paginas_de_produtos_com_permissao_estoque(): void
    {
        $this->get('/dashboard/produtos')->assertRedirect('/dashboard/login');
        $this->actingAs($this->administrador(['contabilidade']), 'dashboard');
        $this->get('/dashboard/produtos')->assertForbidden();
        $this->getJson('/dashboard/api/admin/produtos')->assertForbidden();
        $this->actingAs($this->administrador(), 'dashboard');
        $this->get('/dashboard/produtos/novo')->assertOk();
        $this->getJson('/dashboard/api/usuarios')->assertNotFound();
        $this->getJson('/dashboard/api/admin/produtos')->assertUnauthorized();
    }

    public function test_oauth_dashboard_preserva_identidade_e_encaminha_crud_e_fotos(): void
    {
        Storage::fake('public');
        config(['bff.client_secret' => str_repeat('s', 64)]);
        $this->seed(BffClientSeeder::class);
        $this->mock(\App\Services\ProdutoMovimentacaoService::class)
            ->shouldReceive('contarEstoque')->andReturn(0.0)
            ->shouldReceive('contarReservado')->andReturn(0.0);
        $admin = $this->administrador();
        $this->postJson('/dashboard/login', ['login' => $admin->login, 'password' => 'senha-segura'])->assertOk();
        $url = $this->postJson('/dashboard/authorize')->assertOk()->json('authorization_url');
        $authorization = $this->get($url)->assertRedirect();
        $callback = $authorization->headers->get('Location');
        $this->get($callback)->assertRedirect('/dashboard/');
        $this->getJson('/dashboard/session')->assertOk()->assertJsonPath('api_autorizada', true);
        $this->assertGuest('web');

        $produto = $this->postJson('/dashboard/api/admin/produtos', [
            'codigo' => 'PAINEL-1', 'nome' => 'Produto do painel', 'categoria' => 'camiseta', 'preco' => '12.50',
        ])->assertCreated()->json('data.id');
        $this->getJson('/dashboard/api/admin/produtos?per_page=10')->assertOk()->assertJsonPath('data.total', 1);
        $this->patchJson('/dashboard/api/admin/produtos/'.$produto, ['ativo' => false, 'nome' => 'Editado'])->assertOk();
        $this->getJson('/dashboard/api/admin/produtos/'.$produto)->assertOk()->assertJsonPath('data.nome', 'Editado');
        $this->postJson('/dashboard/api/admin/produtos/'.$produto.'/precos', ['preco' => '15.00'])->assertCreated();
        $imagem = $this->postJson('/dashboard/api/admin/produtos/'.$produto.'/imagens', ['path' => 'https://example.test/foto.jpg'])->assertCreated()->json('data.id');
        $upload = $this->post('/dashboard/api/admin/produtos/'.$produto.'/imagens/upload', [
            'arquivo' => UploadedFile::fake()->image('foto-colada.png', 200, 200), 'principal' => '1',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.principal', true);
        $this->assertCount(1, Storage::disk('public')->allFiles('produtos/'.$produto));
        $this->assertStringContainsString('/storage/produtos/'.$produto.'/', $upload->json('data.path'));
        $this->patchJson('/dashboard/api/admin/produtos/'.$produto.'/imagens/'.$imagem, ['principal' => true])->assertOk();
        $this->deleteJson('/dashboard/api/admin/produtos/'.$produto.'/imagens/'.$imagem)->assertOk();
        $this->assertSoftDeleted('produto_imagens', ['id' => $imagem]);
        $this->deleteJson('/dashboard/api/admin/produtos/'.$produto)->assertOk();
        $this->assertSoftDeleted('produtos', ['id' => $produto]);
        $this->getJson('/dashboard/api/admin/produtos/'.$produto)->assertNotFound();
        $this->postJson('/dashboard/logout')->assertOk();
        $this->assertDatabaseMissing('oauth_access_tokens', ['user_id' => $admin->id, 'revoked' => false]);
    }

    public function test_callback_rejeita_state_incorreto(): void
    {
        config(['bff.client_secret' => str_repeat('s', 64)]);
        $this->actingAs($this->administrador(), 'dashboard');
        $this->postJson('/dashboard/authorize')->assertOk();
        $this->getJson('/dashboard/callback?code=invalido&state='.str_repeat('x', 64))->assertUnprocessable();
        $this->getJson('/dashboard/session')->assertJsonPath('api_autorizada', false);
    }

    public function test_upload_valida_conteudo_tamanho_e_dimensoes_sem_salvar_arquivos_invalidos(): void
    {
        Storage::fake('public');
        Passport::actingAs($this->administrador(), ['admin:manage']);
        $produto = $this->produto();
        $url = '/api/admin/produtos/'.$produto->id.'/imagens/upload';
        foreach ([
            UploadedFile::fake()->create('script.php', 1, 'text/plain'),
            UploadedFile::fake()->createWithContent('falsa.jpg', '<?php echo 1;'),
            UploadedFile::fake()->image('grande.jpg')->size(5121),
            UploadedFile::fake()->image('larga.png', 6001, 1),
            UploadedFile::fake()->createWithContent('vetor.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ] as $arquivo) {
            $this->post($url, ['arquivo' => $arquivo], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('arquivo', 'data.errors');
        }
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('produto_imagens', 0);
    }

    public function test_upload_preserva_arquivo_apos_exclusao_logica_e_mantem_principal_unica(): void
    {
        Storage::fake('public');
        Passport::actingAs($this->administrador(), ['admin:manage']);
        $produto = $this->produto();
        $url = '/api/admin/produtos/'.$produto->id.'/imagens';
        $primeira = $this->post($url.'/upload', ['arquivo' => UploadedFile::fake()->image('primeira.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.principal', true)->json('data.id');
        $segunda = $this->post($url.'/upload', ['arquivo' => UploadedFile::fake()->image('segunda.png'), 'principal' => '1'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.principal', true)->json('data.id');
        $this->assertDatabaseHas('produto_imagens', ['id' => $primeira, 'principal' => false]);
        $this->deleteJson($url.'/'.$segunda)->assertOk();
        $this->assertSoftDeleted('produto_imagens', ['id' => $segunda]);
        $this->assertDatabaseHas('produto_imagens', ['id' => $primeira, 'principal' => true]);
        $this->assertCount(2, Storage::disk('public')->allFiles('produtos/'.$produto->id));
    }

    public function test_upload_exige_autenticacao_escopo_e_permissao_de_estoque(): void
    {
        Storage::fake('public');
        $url = '/api/admin/produtos/'.$this->produto()->id.'/imagens/upload';
        $this->postJson($url)->assertUnauthorized();
        Passport::actingAs($this->administrador(['contabilidade']), ['admin:manage']);
        $this->postJson($url)->assertForbidden();
        Passport::actingAs($this->administrador(), ['catalog:read']);
        $this->postJson($url)->assertForbidden();
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }
}
