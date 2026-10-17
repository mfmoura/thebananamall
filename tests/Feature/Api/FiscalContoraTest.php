<?php

namespace Tests\Feature\Api;

use App\Models\NotaFiscal;
use App\Models\Produto;
use App\Models\Usuario;
use App\Services\CarrinhoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Mockery\MockInterface;
use Tests\TestCase;

class FiscalContoraTest extends TestCase
{
    use RefreshDatabase;

    private const REMOTO = '11111111-1111-4111-8111-111111111111';

    private const CHAVE = '22222222-2222-4222-8222-222222222222';

    private string $situacao = 'draft';

    private bool $falharCriacao = false;

    private array $chavesCriacao = [];

    private string $ambienteToken = 'homologacao';

    private ?string $xmlResposta = null;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.fiscal_contora.token' => 'token-ficticio', 'services.fiscal_contora.cnpj' => '12345678000199', 'services.fiscal_contora.ambiente' => 'homologacao']);
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->mock(CarrinhoService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(static fn ($callback) => $callback());
        });
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => ['contabilidade']]);
        Passport::actingAs($usuario, ['admin:manage']);
        Produto::query()->create(['codigo' => 'LCB-BANDEIRA', 'nome' => 'Bandeira LCB', 'categoria' => 'bandeira', 'ativo' => true]);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/me')) {
                return Http::response(['ok' => true, 'token' => ['environment' => $this->ambienteToken]]);
            }
            if (str_ends_with($request->url(), '/nfe/drafts')) {
                $this->chavesCriacao[] = $request->header('Idempotency-Key')[0];
                if ($this->falharCriacao) {
                    $this->falharCriacao = false;
                    throw new ConnectionException('Timeout simulado');
                }

                return Http::response(['ok' => true, 'data' => ['id' => self::REMOTO]], 201);
            }
            if (str_ends_with($request->url(), '/dispatch')) {
                return Http::response(['ok' => true, 'data' => ['id' => self::REMOTO]], 202);
            }
            if (str_ends_with($request->url(), '/status')) {
                return Http::response(['ok' => true, 'data' => [
                    'id' => self::REMOTO,
                    'lifecycle' => ['status' => $this->situacao, 'processing_status' => 'completed'],
                    'sefaz' => $this->situacao === 'authorized' ? [
                        'status_code' => '100', 'access_key' => '35261012345678000199550010000000011000000010',
                        'protocol' => '135260000000001', 'authorized_at' => '2026-10-16T12:01:00-03:00',
                    ] : ['status_code' => '539', 'status_message' => 'Rejeição de teste'],
                ]]);
            }
            if (str_ends_with($request->url(), '/artifacts/xml_authorized')) {
                return Http::response($this->xmlResposta ?? file_get_contents(base_path('tests/Fixtures/nfe-homologacao.xml')), 200, ['Content-Type' => 'application/xml']);
            }
            throw new \RuntimeException('Chamada inesperada: '.$request->url());
        });
    }

    public function test_emissao_e_idempotente_e_nao_movimenta_estoque_de_homologacao(): void
    {
        $dados = $this->dados();
        $primeira = $this->postJson('/api/admin/notas-fiscais/emitir', $dados)->assertAccepted();
        $this->postJson('/api/admin/notas-fiscais/emitir', $dados)->assertAccepted()
            ->assertJsonPath('data.id', $primeira->json('data.id'));
        $this->assertDatabaseCount('notas_fiscais', 1);
        $this->assertDatabaseCount('produto_movimentacoes', 0);
        $this->assertCount(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/nfe/drafts')));
        $this->assertCount(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/dispatch')));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/nfe/drafts')
            && $request['environment'] === 'homologacao'
            && $request['payload']['items'][0]['code'] === 'LCB-BANDEIRA'
            && $request->header('Idempotency-Key')[0] === self::CHAVE);
        $nota = NotaFiscal::query()->firstOrFail();
        $this->assertArrayNotHasKey('emissao_payload', $nota->toArray());
        $this->assertStringNotContainsString('12345678909', $nota->getRawOriginal('emissao_payload'));
    }

    public function test_reutilizar_chave_com_outros_dados_retorna_conflito(): void
    {
        $dados = $this->dados();
        $this->postJson('/api/admin/notas-fiscais/emitir', $dados)->assertAccepted();
        $dados['itens'][0]['quantidade'] = 2;
        $this->postJson('/api/admin/notas-fiscais/emitir', $dados)->assertConflict();
        $this->assertDatabaseCount('notas_fiscais', 1);
    }

    public function test_timeout_na_criacao_pode_ser_repetido_sem_perder_a_chave(): void
    {
        $this->falharCriacao = true;
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertStatus(503);
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertAccepted();
        $this->assertDatabaseCount('notas_fiscais', 1);
        $this->assertSame([self::CHAVE, self::CHAVE], $this->chavesCriacao);
    }

    public function test_sincronizacao_autorizada_armazena_xml_e_gera_pdf_local(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertAccepted()->json('data.id');
        $this->situacao = 'authorized';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk()->assertJsonPath('data.status', 'autorizada');
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk();
        Storage::disk('local')->assertExists("fiscal/homologacao/$id/nfe.xml");
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get("fiscal/homologacao/$id/danfe.pdf"));
        $this->get("/api/admin/notas-fiscais/$id/danfe")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("/api/admin/notas-fiscais/$id/xml")->assertOk()->assertHeader('Content-Type', 'application/xml');
        $this->assertDatabaseCount('produto_movimentacoes', 0);
        $this->assertCount(1, Http::recorded(fn ($request) => str_ends_with($request->url(), '/artifacts/xml_authorized')));
    }

    public function test_rejeicao_nao_libera_danfe(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertAccepted()->json('data.id');
        $this->situacao = 'error';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk()->assertJsonPath('data.status', 'rejeitada');
        $this->getJson("/api/admin/notas-fiscais/$id/danfe")->assertConflict();
    }

    public function test_administrador_sem_contabilidade_nao_pode_emitir(): void
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => ['estoque']]);
        Passport::actingAs($usuario, ['admin:manage']);
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_producao_e_bloqueada_antes_de_chamar_o_provedor(): void
    {
        config(['services.fiscal_contora.ambiente' => 'producao']);
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_chave_de_producao_e_recusada_mesmo_com_configuracao_de_homologacao(): void
    {
        $this->ambienteToken = 'producao';
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertStatus(503);
        $this->assertDatabaseCount('notas_fiscais', 0);
        Http::assertSentCount(1);
    }

    public function test_xml_de_outro_ambiente_nao_gera_danfe_e_pode_ser_recuperado(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertAccepted()->json('data.id');
        $this->situacao = 'authorized';
        $this->xmlResposta = str_replace('<tpAmb>2</tpAmb>', '<tpAmb>1</tpAmb>', file_get_contents(base_path('tests/Fixtures/nfe-homologacao.xml')));
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertStatus(502);
        Storage::disk('local')->assertMissing("fiscal/homologacao/$id/danfe.pdf");
        $this->xmlResposta = null;
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk();
        Storage::disk('local')->assertExists("fiscal/homologacao/$id/danfe.pdf");
        $this->assertDatabaseCount('notas_fiscais', 1);
    }

    public function test_logistica_pode_baixar_danfe_mas_nao_emitir(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertAccepted()->json('data.id');
        $this->situacao = 'authorized';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk();
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->logistica()->create(['nome' => 'Expedição LCB', 'webhook' => 'https://example.test/entregas']);
        Passport::actingAs($usuario, ['logistics:manage']);
        $this->get("/api/logistica/notas-fiscais/$id/danfe")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertForbidden();
        $this->getJson("/api/admin/notas-fiscais/$id/xml")->assertForbidden();
    }

    public function test_escopo_oauth_e_obrigatorio_mesmo_para_contabilidade(): void
    {
        Passport::actingAs(auth('api')->user(), ['catalog:read']);
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_validacao_fiscal_rejeita_ncm_invalido_antes_da_integracao(): void
    {
        $dados = $this->dados();
        $dados['itens'][0]['ncm'] = 'invalido';
        $this->postJson('/api/admin/notas-fiscais/emitir', $dados)->assertUnprocessable()
            ->assertJsonValidationErrors('itens.0.ncm', 'data.errors');
        Http::assertNothingSent();
    }

    private function dados(): array
    {
        return [
            'idempotency_key' => self::CHAVE, 'natureza_operacao' => 'venda', 'numero' => 1, 'serie' => 1,
            'consumidor_final' => true, 'finalidade' => 1,
            'destinatario' => [
                'nome' => 'Cliente de homologação', 'documento' => '12345678909', 'indicador_ie' => 9,
                'endereco' => ['logradouro' => 'Rua de Teste', 'numero' => '2', 'bairro' => 'Centro', 'cidade' => 'São Paulo', 'codigo_municipio' => '3550308', 'estado' => 'SP', 'cep' => '01001000'],
            ],
            'pagamentos' => [['meio' => '17', 'valor' => 25]],
            'itens' => [['produto_id' => Produto::query()->firstOrFail()->id, 'ncm' => '63079090', 'cfop' => '5102', 'unidade_comercial' => 'UN', 'quantidade' => 1, 'valor_unitario' => 25, 'tributos' => ['icms' => ['code' => '102', 'origin' => 0]]]],
        ];
    }
}
