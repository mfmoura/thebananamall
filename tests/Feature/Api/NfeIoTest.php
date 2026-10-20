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
use Tests\TestCase;

class NfeIoTest extends TestCase
{
    use RefreshDatabase;

    private string $status = 'Processing';

    private string $ambiente = 'Test';

    private bool $timeout = false;

    private int $tentativasEmissao = 0;

    private string $xml;

    private array $dados;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nfe_io' => [
            'token' => 'token-ficticio', 'company_id' => 'empresa1234567890', 'state_tax_id' => 'inscricao12345678',
            'cnpj' => '12345678000199', 'uf_emitente' => 'SP', 'serie' => 1, 'ambiente' => 'homologacao',
            'download_hosts' => ['xml.example.test'],
        ]]);
        Storage::fake('local');
        Http::preventStrayRequests();
        $this->mock(CarrinhoService::class)->shouldReceive('comBloqueioDeEstoque')->andReturnUsing(fn ($callback) => $callback());
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => ['contabilidade']]);
        Passport::actingAs($usuario, ['admin:manage']);
        $produto = Produto::query()->create(['codigo' => 'LCB-TESTE', 'nome' => 'Bandeira', 'categoria' => 'bandeira', 'ativo' => true]);
        $this->dados = json_decode(file_get_contents(base_path('docs/exemplos/nfeio-emissao.json')), true);
        $this->dados['itens'][0]['produto_id'] = $produto->id;
        $this->dados['numero'] = 1;
        $this->dados['serie'] = 1;
        $this->xml = file_get_contents(base_path('tests/Fixtures/nfe-homologacao.xml'));
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/companies/empresa1234567890')) {
                return Http::response(['company' => ['federalTaxNumber' => 12345678000199]]);
            }
            if (str_ends_with($request->url(), '/statetaxes/inscricao12345678')) {
                return Http::response(['stateTax' => ['environmentType' => $this->ambiente, 'type' => 'nFe', 'code' => 'SP', 'serie' => 1]]);
            }
            if ($request->method() === 'POST') {
                $this->tentativasEmissao++;
                if ($this->timeout) {
                    throw new ConnectionException('Timeout simulado');
                }

                return Http::response(['id' => '9945c26da840f80a']);
            }
            if (str_ends_with($request->url(), '/xml')) {
                return Http::response(['uri' => 'https://xml.example.test/nota.xml?assinatura=ficticia']);
            }
            if (str_starts_with($request->url(), 'https://xml.example.test/')) {
                $this->assertFalse($request->hasHeader('Authorization'));

                return Http::response($this->xml, 200, ['Content-Type' => 'application/xml']);
            }

            return Http::response([
                'id' => '9945c26da840f80a', 'number' => 1, 'serie' => 1, 'status' => $this->status,
                'issuer' => ['federalTaxNumber' => '12345678000199'],
                'authorization' => ['accessKey' => '35261012345678000199550010000000011000000010'],
            ]);
        });
    }

    public function test_emissao_idempotente_usa_ie_explicita_e_payload_nfeio(): void
    {
        $primeira = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted();
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted()->assertJsonPath('data.id', $primeira->json('data.id'));
        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/statetaxes/inscricao12345678/productinvoices')
            && $r['items'][0]['tax']['icms']['csosn'] === '102'
            && $r['payment'][0]['paymentDetail'][0]['method'] === 'InstantPayment'
            && $r->header('Authorization')[0] === 'token-ficticio');
        $this->assertSame(1, $this->tentativasEmissao);
        $this->assertDatabaseCount('notas_fiscais', 1);
        $this->assertDatabaseCount('produto_movimentacoes', 0);
        $nota = NotaFiscal::firstOrFail();
        $this->assertArrayNotHasKey('emissao_payload', $nota->toArray());
        $this->assertStringNotContainsString($this->dados['destinatario']['documento'], $nota->getRawOriginal('emissao_payload'));
    }

    public function test_timeout_bloqueia_novo_post_e_preserva_nota(): void
    {
        $this->timeout = true;
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertStatus(503);
        $this->timeout = false;
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertConflict();
        $this->assertSame(1, $this->tentativasEmissao);
        $this->assertDatabaseCount('notas_fiscais', 1);
    }

    public function test_ie_producao_bloqueia_emissao(): void
    {
        $this->ambiente = 'Production';
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertStatus(503);
        $this->assertCount(0, Http::recorded(fn ($r) => $r->method() === 'POST'));
        $this->assertDatabaseCount('notas_fiscais', 0);
    }

    public function test_configuracao_producao_nao_chama_provedor(): void
    {
        config(['services.nfe_io.ambiente' => 'producao']);
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_mesma_chave_com_outros_dados_retorna_conflito(): void
    {
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted();
        $this->dados['itens'][0]['quantidade'] = 2;
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertConflict();
        $this->assertSame(1, $this->tentativasEmissao);
    }

    public function test_numero_de_nota_excluida_permanece_reservado(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted()->json('data.id');
        NotaFiscal::findOrFail($id)->delete();
        $this->dados['idempotency_key'] = '33333333-3333-4333-8333-333333333333';
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertConflict();
        $this->assertSame(1, $this->tentativasEmissao);
    }

    public function test_rateio_do_frete_preserva_centavos_e_total_do_pagamento(): void
    {
        $this->dados['itens'][] = $this->dados['itens'][0];
        $this->dados['total_frete'] = 0.03;
        $this->dados['modalidade_frete'] = 0;
        $this->dados['pagamentos'][0]['valor'] = 50.03;
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted();
        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && abs(array_sum(array_column($r['items'], 'freightAmount')) - 0.03) < 0.001
            && $r['transport']['freightModality'] === 'ByIssuer'
            && $r['payment'][0]['paymentDetail'][0]['amount'] === 50.03);
    }

    public function test_st_nao_e_inventada_nem_enviada_ao_provedor(): void
    {
        $this->dados['itens'][0]['tributos']['icms']['code'] = '201';
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertUnprocessable();
        $this->assertSame(0, $this->tentativasEmissao);
    }

    public function test_sincroniza_xml_e_danfe_sem_reemitir_ou_movimentar_estoque(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted()->json('data.id');
        $this->status = 'Issued';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk()->assertJsonPath('data.status', 'autorizada');
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk();
        $this->get("/api/admin/notas-fiscais/$id/danfe")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("/api/admin/notas-fiscais/$id/xml")->assertOk();
        Storage::disk('local')->assertExists("fiscal/homologacao/$id/danfe.pdf");
        $this->assertDatabaseCount('produto_movimentacoes', 0);
        $this->assertCount(1, Http::recorded(fn ($r) => $r->method() === 'POST'));
    }

    public function test_xml_producao_nao_autoriza_nota(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted()->json('data.id');
        $this->status = 'Issued';
        $this->xml = str_replace('<tpAmb>2</tpAmb>', '<tpAmb>1</tpAmb>', $this->xml);
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertStatus(502);
        $this->assertFalse(NotaFiscal::findOrFail($id)->estaAutorizada());
        Storage::disk('local')->assertMissing("fiscal/homologacao/$id/danfe.pdf");
    }

    public function test_host_xml_nao_autorizado_nao_recebe_requisicao(): void
    {
        config(['services.nfe_io.download_hosts' => []]);
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted()->json('data.id');
        $this->status = 'Issued';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertStatus(502);
        Http::assertNotSent(fn ($r) => str_starts_with($r->url(), 'https://xml.example.test/'));
    }

    public function test_rejeicao_e_contingencia_nao_liberam_documentos(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted()->json('data.id');
        $this->status = 'IssuedContingency';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk()->assertJsonPath('data.status', 'processando');
        $this->status = 'Error';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk()->assertJsonPath('data.status', 'rejeitada');
        $this->getJson("/api/admin/notas-fiscais/$id/danfe")->assertConflict();
    }

    public function test_sem_permissao_nao_chama_provedor(): void
    {
        Passport::actingAs(auth('api')->user(), ['catalog:read']);
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_sem_contabilidade_nao_emite(): void
    {
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->administrador()->create(['paginas' => ['estoque']]);
        Passport::actingAs($usuario, ['admin:manage']);
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_logistica_baixa_danfe_mas_nao_emite_ou_le_xml(): void
    {
        $id = $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertAccepted()->json('data.id');
        $this->status = 'Issued';
        $this->postJson("/api/admin/notas-fiscais/$id/sincronizar")->assertOk();
        $usuario = Usuario::factory()->create(['email_validado' => true]);
        $usuario->logistica()->create(['nome' => 'Logística', 'webhook' => 'https://example.test/entregas']);
        Passport::actingAs($usuario, ['logistics:manage']);
        $this->get("/api/logistica/notas-fiscais/$id/danfe")->assertOk();
        $this->postJson('/api/admin/notas-fiscais/emitir', $this->dados)->assertForbidden();
        $this->getJson("/api/admin/notas-fiscais/$id/xml")->assertForbidden();
    }
}
