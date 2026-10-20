<?php

namespace Tests\Integration;

use App\Enums\StatusPedido;
use App\Jobs\ProcessarPedidoPagamento;
use App\Models\FormaPagamento;
use App\Models\Pedido;
use App\Models\PedidoPagamento;
use App\Models\Produto;
use App\Models\Usuario;
use App\Services\MercadoPagoService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Execução explícita, fora das suites padrão. Usa o provedor real com cartões oficiais fictícios. */
class MercadoPagoHomologacaoTest extends TestCase
{
    use DatabaseMigrations;

    public function test_api_worker_e_conciliacao_com_provedor_de_teste(): void
    {
        if (getenv('MP_HOMOLOGACAO_TESTE_CONFIRMADO') !== '1') {
            $this->markTestSkipped('Exige confirmação explícita de credenciais de teste.');
        }
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertNotEmpty(config('mercadopago.access_token'));
        Queue::fake(); // Não publica dados do banco efêmero em filas da loja.

        $casos = [
            ['pix', 'APRO', null, null],
            ['boleto', 'APRO', null, null],
            ['credit_card', 'APRO', 'visa', '4235647728025682'],
            ['credit_card', 'APRO', 'master', '5480832801033311'],
            ['credit_card', 'APRO', 'amex', '375365153556885'],
        ];
        foreach (['OTHE', 'CONT', 'CALL', 'FUND', 'SECU', 'EXPI', 'FORM'] as $titular) {
            $casos[] = ['credit_card', $titular, 'visa', '4235647728025682'];
        }
        $casos[] = ['debit_card', 'APRO', 'debelo', '5067766783888311'];
        foreach ($casos as [$meio, $titular, $bandeira, $numero]) {
            $usuario = Usuario::factory()->create(['email' => 'homologacao.'.fake()->uuid().'@example.com', 'email_validado' => true]);
            $cliente = $usuario->cliente()->create();
            Passport::actingAs($usuario, ['orders:write']);
            $codigo = match ($meio) { 'credit_card' => 'cartao_credito', 'debit_card' => 'cartao_debito', default => $meio };
            $forma = FormaPagamento::firstOrCreate(['codigo' => $codigo], ['nome' => $codigo, 'ativo' => true]);
            $produto = Produto::create(['codigo' => fake()->uuid(), 'nome' => 'Homologação', 'categoria' => 'bandeira', 'ativo' => true]);
            $preco = $produto->precos()->create(['preco' => '50.00', 'data' => now()]);
            // IDs altos evitam referência a pedidos normais em callbacks da conta de teste.
            $pedido = Pedido::forceCreate(['id' => random_int(1000000000, 2000000000), 'cliente_id' => $cliente->id,
                'forma_pagamento_id' => $forma->id, 'status' => StatusPedido::AguardandoPagamento,
                'data' => now(), 'idempotency_key' => fake()->uuid()]);
            $pedido->itens()->create(['codigo' => $produto->id, 'preco_id' => $preco->id, 'preco' => '50.00', 'quantidade' => 1, 'data' => now()]);
            $pedido->processamentoFiscal()->create(['chave' => fake()->uuid(), 'etapa' => 'aguardando_pagamento', 'dados_cliente' => [
                'nome' => 'APRO Teste', 'documento' => '12345678909', 'endereco' => [
                    'logradouro' => 'Rua de Teste', 'numero' => '2', 'bairro' => 'Centro', 'cidade' => 'Sao Paulo', 'estado' => 'SP', 'cep' => '01001000'],
            ]]);
            $dados = ['meio' => $meio];
            if ($numero) {
                $resposta = Http::timeout(20)->post('https://api.mercadopago.com/v1/card_tokens?public_key='.config('mercadopago.public_key'), [
                    'card_number' => $numero, 'security_code' => $bandeira === 'amex' ? '1234' : '123',
                    'expiration_month' => 11, 'expiration_year' => 2030,
                    'cardholder' => ['name' => $titular, 'identification' => ['type' => 'CPF', 'number' => '12345678909']],
                ]);
                $this->assertTrue($resposta->successful(), 'Falha de tokenização (corpo omitido).');
                $this->assertIsString($token = $resposta->json('id'));
                $dados += ['token' => $token, 'payment_method_id' => $bandeira, 'parcelas' => 1];
            }
            $url = '/api/pedidos/'.$pedido->id.'/pagamento';
            $this->postJson($url, $dados)->assertAccepted()->assertJsonMissingPath('data.dados_envio');
            $pagamento = PedidoPagamento::where('pedido_id', $pedido->id)->sole();
            Queue::assertPushed(ProcessarPedidoPagamento::class, fn ($job) => $job->pagamentoId === $pagamento->id);
            try {
                (new ProcessarPedidoPagamento($pagamento->id))->withFakeQueueInteractions()->handle(app(MercadoPagoService::class));
            } catch (HttpException $e) {
                if ($meio !== 'debit_card') throw $e;
                fwrite(STDOUT, json_encode(['meio' => $meio, 'bandeira' => $bandeira, 'resultado' => 'nao_homologado', 'http_local' => $e->getStatusCode()]).PHP_EOL);
                continue;
            }
            $pagamento->refresh();
            $this->assertStringStartsWith('ORDTST', $pagamento->order_id);
            $this->assertNull($pagamento->dados_envio);
            $this->getJson($url)->assertOk()->assertJsonPath('data.order_id', $pagamento->order_id);
            $campos = array_keys($pagamento->instrucoes ?? []);
            if ($meio === 'boleto') {
                $this->assertContains('digitable_line', $campos);
                $this->assertContains('ticket_url', $campos);
                $this->assertSame(StatusPedido::AguardandoPagamento, $pedido->fresh()->status);
            } elseif ($titular === 'APRO') {
                if ($meio === 'pix' && $pagamento->status !== 'processed') {
                    $this->assertContains('qr_code', $campos);
                }
                for ($tentativa = 0; $tentativa < 4 && $pagamento->status !== 'processed'; $tentativa++) {
                    usleep(500000);
                    app(MercadoPagoService::class)->sincronizar($pagamento->order_id);
                    $pagamento->refresh();
                }
                $this->assertSame(StatusPedido::Pago, $pedido->fresh()->status);
            } else {
                $this->assertSame($titular === 'CONT' ? 'processing' : 'failed', $pagamento->status);
                $this->assertSame(StatusPedido::AguardandoPagamento, $pedido->fresh()->status);
            }
            // Chamada local assinada; não atesta entrega automática pelo Mercado Pago.
            $ts = (string) now()->getTimestampMs();
            $requestId = fake()->uuid();
            $manifesto = 'id:'.$pagamento->order_id.';request-id:'.$requestId.';ts:'.$ts.';';
            $assinatura = hash_hmac('sha256', $manifesto, config('mercadopago.webhook_secret'));
            $this->withHeaders(['x-request-id' => $requestId, 'x-signature' => "ts=$ts,v1=$assinatura"])
                ->postJson('/api/webhooks/mercado-pago?data.id='.$pagamento->order_id, ['type' => 'order'])->assertOk();
            $this->postJson($url, $dados)->assertAccepted();
            $this->assertSame(1, PedidoPagamento::where('pedido_id', $pedido->id)->count());
            fwrite(STDOUT, json_encode(['meio' => $meio, 'bandeira' => $bandeira, 'titular' => $titular,
                'order_id' => $pagamento->order_id, 'status' => $pagamento->fresh()->status,
                'processamento' => $pagamento->fresh()->processamento, 'instrucoes_presentes' => $campos], JSON_UNESCAPED_UNICODE).PHP_EOL);
        }
    }
}
