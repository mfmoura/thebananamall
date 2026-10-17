<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrinhoController;
use App\Http\Controllers\Api\AdministradorController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\EnderecoController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\LogisticaController;
use App\Http\Controllers\Api\NotaFiscalController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\PrecoController;
use App\Http\Controllers\Api\ProdutoController;
use App\Http\Controllers\Api\ProdutoImagemController;
use App\Http\Controllers\Api\ProdutoMovimentacaoController;
use App\Http\Controllers\Api\RecuperacaoSenhaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VisitanteController;
use Illuminate\Support\Facades\Route;

Route::post('/visitantes', [VisitanteController::class, 'store'])->middleware('throttle:20,1');
Route::post('/email/confirmar', [EmailVerificationController::class, 'verify'])->middleware('throttle:10,1');
Route::post('/email/reenviar-confirmacao', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:5,1');
Route::post('/senha/recuperar', [RecuperacaoSenhaController::class, 'solicitar'])->middleware('throttle:5,1');
Route::post('/senha/redefinir', [RecuperacaoSenhaController::class, 'redefinir'])->middleware('throttle:5,1');

// Compatibilidade para cadastros de Cliente; perfis privilegiados usam rotas administrativas.
Route::post('/usuarios', [ClienteController::class, 'store'])->middleware('throttle:10,1');
Route::post('/clientes', [ClienteController::class, 'store'])->middleware('throttle:10,1');
Route::get('/produtos', [ProdutoController::class, 'index']);
Route::get('/formas-pagamento', [\App\Http\Controllers\Api\FormaPagamentoController::class, 'index']);
Route::get('/produtos/{produto}', [ProdutoController::class, 'show'])->whereNumber('produto');
Route::get('/produtos/{produto}/precos', [PrecoController::class, 'index'])->whereNumber('produto');
Route::get('/produtos/{produto}/estoque', [ProdutoMovimentacaoController::class, 'contarEstoque'])
    ->whereNumber('produto');

// O carrinho pode ser operado por um Visitante (X-Visitante-Token) ou por Cliente (OAuth2 Bearer).
Route::get('/carrinho', [CarrinhoController::class, 'show']);
Route::post('/carrinho', [CarrinhoController::class, 'store']);
Route::put('/carrinho', [CarrinhoController::class, 'update']);
Route::delete('/carrinho', [CarrinhoController::class, 'destroy']);
Route::post('/carrinho/itens', [CarrinhoController::class, 'adicionarItem']);
Route::patch('/carrinho/itens/{codigo}', [CarrinhoController::class, 'atualizarItem'])->whereNumber('codigo');
Route::delete('/carrinho/itens/{codigo}', [CarrinhoController::class, 'excluirItem'])->whereNumber('codigo');

Route::middleware(['auth:api', 'conta-pronta'])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->whereNumber('cliente');
    Route::put('/clientes/{cliente}', [ClienteController::class, 'update'])->whereNumber('cliente');
    Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->whereNumber('cliente');

    Route::get('/enderecos/cep/{cep}', [EnderecoController::class, 'consultarCep'])->middleware('throttle:30,1');
    Route::post('/enderecos', [EnderecoController::class, 'store']);
    Route::get('/enderecos/{endereco}', [EnderecoController::class, 'show'])->whereNumber('endereco');
    Route::put('/enderecos/{endereco}', [EnderecoController::class, 'update'])->whereNumber('endereco');
    Route::delete('/enderecos/{endereco}', [EnderecoController::class, 'destroy'])->whereNumber('endereco');

    Route::middleware(['perfil:administrador', 'scope:admin:manage'])->prefix('admin')->group(function (): void {
        Route::post('/administradores', [AdministradorController::class, 'store']);
        Route::get('/administradores/{administrador}', [AdministradorController::class, 'show'])
            ->whereNumber('administrador');
        Route::put('/administradores/{administrador}', [AdministradorController::class, 'update'])
            ->whereNumber('administrador');
        Route::delete('/administradores/{administrador}', [AdministradorController::class, 'destroy'])
            ->whereNumber('administrador');

        Route::post('/logisticas', [LogisticaController::class, 'store']);
        Route::get('/logisticas/{logistica}', [LogisticaController::class, 'show'])->whereNumber('logistica');
        Route::put('/logisticas/{logistica}', [LogisticaController::class, 'update'])->whereNumber('logistica');
        Route::delete('/logisticas/{logistica}', [LogisticaController::class, 'destroy'])->whereNumber('logistica');
    });

    Route::middleware(['perfil:administrador', 'scope:admin:manage'])
        ->prefix('admin')->group(function (): void {
            Route::post('/notas-fiscais', [NotaFiscalController::class, 'store'])
                ->middleware('pagina-admin:contabilidade');
            Route::middleware('pagina-admin:contabilidade')->group(function (): void {
                Route::post('/notas-fiscais/emitir', [NotaFiscalController::class, 'emitir'])->middleware('throttle:5,1');
                Route::get('/notas-fiscais/{notaFiscal}', [NotaFiscalController::class, 'show'])->whereNumber('notaFiscal');
                Route::post('/notas-fiscais/{notaFiscal}/sincronizar', [NotaFiscalController::class, 'sincronizar'])
                    ->whereNumber('notaFiscal')->middleware('throttle:10,1');
                Route::get('/notas-fiscais/{notaFiscal}/xml', [NotaFiscalController::class, 'xml'])->whereNumber('notaFiscal');
                Route::get('/notas-fiscais/{notaFiscal}/danfe', [NotaFiscalController::class, 'danfe'])->whereNumber('notaFiscal');
            });

            Route::post('/estoque/movimentacoes', [ProdutoMovimentacaoController::class, 'store'])
                ->middleware('pagina-admin:estoque,manutencao');

            Route::middleware('pagina-admin:estoque')->group(function (): void {
                Route::post('/produtos', [ProdutoController::class, 'store']);
                Route::put('/produtos/{produto}', [ProdutoController::class, 'update'])->whereNumber('produto');
                Route::patch('/produtos/{produto}', [ProdutoController::class, 'update'])->whereNumber('produto');
                Route::delete('/produtos/{produto}', [ProdutoController::class, 'destroy'])->whereNumber('produto');
                Route::post('/produtos/{produto}/precos', [PrecoController::class, 'store'])->whereNumber('produto');
                Route::delete('/precos/{preco}', [PrecoController::class, 'destroy'])->whereNumber('preco');
                Route::post('/produtos/{produto}/imagens', [ProdutoImagemController::class, 'store'])
                    ->whereNumber('produto');
                Route::put('/produtos/{produto}/imagens/{imagem}', [ProdutoImagemController::class, 'update'])
                    ->whereNumber(['produto', 'imagem']);
                Route::patch('/produtos/{produto}/imagens/{imagem}', [ProdutoImagemController::class, 'update'])
                    ->whereNumber(['produto', 'imagem']);
                Route::delete('/produtos/{produto}/imagens/{imagem}', [ProdutoImagemController::class, 'destroy'])
                    ->whereNumber(['produto', 'imagem']);
                Route::get('/estoque/movimentacoes', [ProdutoMovimentacaoController::class, 'index']);
                Route::delete('/estoque/movimentacoes/{movimentacao}', [ProdutoMovimentacaoController::class, 'destroy'])
                    ->whereNumber('movimentacao');
            });
        });

    Route::middleware(['perfil:cliente', 'scope:cart:write'])
        ->post('/carrinho/mesclar', [CarrinhoController::class, 'merge']);

    Route::middleware(['perfil:cliente', 'scope:orders:write'])->group(function (): void {
        Route::get('/pedidos', [PedidoController::class, 'index']);
        Route::post('/pedidos', [PedidoController::class, 'store']);
        Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->whereNumber('pedido');
        Route::get('/pedidos/{pedido}/danfe', [PedidoController::class, 'danfe'])->whereNumber('pedido');
        Route::post('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->whereNumber('pedido');
    });

    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show']);
    Route::get('/logistica/notas-fiscais/{notaFiscal}/danfe', [NotaFiscalController::class, 'danfe'])
        ->whereNumber('notaFiscal')->middleware(['perfil:logistica', 'scope:logistics:manage']);
    Route::post('/usuarios/{usuario}/email', [UsuarioController::class, 'updateEmail'])
        ->whereNumber('usuario')
        ->middleware('throttle:3,1');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update']);
    Route::patch('/usuarios/{usuario}', [UsuarioController::class, 'update']);
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy']);

    // Rotas operacionais de Logística podem ser adicionadas aqui com perfil:logistica
    // e o escopo logistics:manage.
});
