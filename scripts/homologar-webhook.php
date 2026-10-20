<?php

// Diagnóstico explícito com registros fictícios persistentes no ambiente local.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! $app->environment('local') || getenv('MP_HOMOLOGACAO_TESTE_CONFIRMADO') !== '1') {
    fwrite(STDERR, "Exige ambiente local e confirmação de credenciais de teste.\n");
    exit(1);
}

$acao = $argv[1] ?? 'consultar';
$referencia = $argv[2] ?? '';
if (! preg_match('/^webhook-homolog-[a-f0-9-]{36}$/D', $referencia)) {
    fwrite(STDERR, "Informe uma referência webhook-homolog-UUID.\n");
    exit(1);
}

$chave = substr($referencia, strlen('webhook-homolog-'));
$pedido = App\Models\Pedido::where('idempotency_key', $chave)->first();
if ($acao === 'preparar' && ! $pedido) {
    $pedido = Illuminate\Support\Facades\DB::transaction(function () use ($referencia, $chave) {
        $usuario = App\Models\Usuario::factory()->create([
            'login' => $referencia, 'email' => $referencia.'@example.com',
            'email_validado' => true, 'password' => Illuminate\Support\Str::random(64),
        ]);
        $cliente = $usuario->cliente()->create();
        $forma = App\Models\FormaPagamento::where('codigo', 'pix')->firstOrFail();
        $produto = App\Models\Produto::create(['codigo' => $referencia, 'nome' => 'Diagnóstico de webhook — teste',
            'categoria' => 'bandeira', 'ativo' => false]);
        $preco = $produto->precos()->create(['preco' => '50.00', 'data' => now()]);
        $pedido = App\Models\Pedido::create(['cliente_id' => $cliente->id, 'forma_pagamento_id' => $forma->id,
            'status' => App\Enums\StatusPedido::AguardandoPagamento, 'data' => now(), 'idempotency_key' => $chave]);
        $pedido->itens()->create(['codigo' => $produto->id, 'preco_id' => $preco->id,
            'preco' => '50.00', 'quantidade' => 1, 'data' => now()]);
        // Snapshot de teste com processamento fiscal desativado para este diagnóstico.
        $pedido->processamentoFiscal()->create(['chave' => (string) Illuminate\Support\Str::uuid(),
            'etapa' => 'cancelado', 'dados_cliente' => ['nome' => 'APRO Teste', 'documento' => '12345678909']]);

        return $pedido;
    });
}
if (! $pedido) {
    fwrite(STDERR, "Diagnóstico não encontrado.\n");
    exit(1);
}
if ($acao === 'preparar') {
    app(App\Services\MercadoPagoService::class)->criar($pedido, ['meio' => 'pix']);
}
$pagamento = App\Models\PedidoPagamento::where('pedido_id', $pedido->id)->first();
$ambiente = Dotenv\Dotenv::parse(file_get_contents(base_path('.env')));
echo json_encode(['referencia' => $referencia, 'pedido_id' => $pedido->id, 'pedido_status' => $pedido->fresh()->status->value,
    'order_id' => $pagamento?->order_id, 'status' => $pagamento?->status, 'processamento' => $pagamento?->processamento,
    'etapa_fiscal' => $pedido->processamentoFiscal?->etapa,
    'config_em_cache' => $app->configurationIsCached(),
    'segredo_corresponde_env' => hash_equals((string) ($ambiente['MERCADO_PAGO_WEBHOOK_SECRET'] ?? ''), (string) config('mercadopago.webhook_secret')),
], JSON_UNESCAPED_UNICODE).PHP_EOL;
