<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarrinhoController;
use App\Http\Controllers\Api\PedidoController;
use App\Http\Controllers\Api\PrecoController;
use App\Http\Controllers\Api\ProdutoController;
use App\Http\Controllers\Api\ProdutoImagemController;
use App\Http\Controllers\Api\ProdutoMovimentacaoController;
use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/usuarios', [UsuarioController::class, 'store']);
Route::get('/produtos', [ProdutoController::class, 'index']);
Route::get('/produtos/{produto}', [ProdutoController::class, 'show'])->whereNumber('produto');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/admin/usuarios', [UsuarioController::class, 'storeAsAdministrator']);

    Route::post('/admin/produtos', [ProdutoController::class, 'store']);
    Route::put('/admin/produtos/{produto}', [ProdutoController::class, 'update'])->whereNumber('produto');
    Route::patch('/admin/produtos/{produto}', [ProdutoController::class, 'update'])->whereNumber('produto');
    Route::delete('/admin/produtos/{produto}', [ProdutoController::class, 'destroy'])->whereNumber('produto');
    Route::get('/admin/produtos/{produto}/precos', [PrecoController::class, 'index'])->whereNumber('produto');
    Route::post('/admin/produtos/{produto}/precos', [PrecoController::class, 'store'])->whereNumber('produto');
    Route::delete('/admin/precos/{preco}', [PrecoController::class, 'destroy'])->whereNumber('preco');
    Route::post('/admin/produtos/{produto}/imagens', [ProdutoImagemController::class, 'store'])
        ->whereNumber('produto');
    Route::put('/admin/produtos/{produto}/imagens/{imagem}', [ProdutoImagemController::class, 'update'])
        ->whereNumber(['produto', 'imagem']);
    Route::patch('/admin/produtos/{produto}/imagens/{imagem}', [ProdutoImagemController::class, 'update'])
        ->whereNumber(['produto', 'imagem']);
    Route::delete('/admin/produtos/{produto}/imagens/{imagem}', [ProdutoImagemController::class, 'destroy'])
        ->whereNumber(['produto', 'imagem']);
    Route::get('/admin/produtos/{produto}/estoque', [ProdutoMovimentacaoController::class, 'contarEstoque'])
        ->whereNumber('produto');
    Route::get('/admin/estoque/movimentacoes', [ProdutoMovimentacaoController::class, 'index']);
    Route::post('/admin/estoque/movimentacoes', [ProdutoMovimentacaoController::class, 'store']);
    Route::delete('/admin/estoque/movimentacoes/{movimentacao}', [ProdutoMovimentacaoController::class, 'destroy'])
        ->whereNumber('movimentacao');

    Route::get('/carrinho', [CarrinhoController::class, 'show']);
    Route::post('/carrinho', [CarrinhoController::class, 'store']);
    Route::put('/carrinho', [CarrinhoController::class, 'update']);
    Route::delete('/carrinho', [CarrinhoController::class, 'destroy']);
    Route::post('/carrinho/itens', [CarrinhoController::class, 'adicionarItem']);
    Route::patch('/carrinho/itens/{codigo}', [CarrinhoController::class, 'atualizarItem'])
        ->whereNumber('codigo');
    Route::delete('/carrinho/itens/{codigo}', [CarrinhoController::class, 'excluirItem'])
        ->whereNumber('codigo');

    Route::get('/pedidos', [PedidoController::class, 'index']);
    Route::post('/pedidos', [PedidoController::class, 'store']);
    Route::get('/pedidos/{pedido}', [PedidoController::class, 'show'])->whereNumber('pedido');
    Route::post('/pedidos/{pedido}/cancelar', [PedidoController::class, 'cancelar'])->whereNumber('pedido');

    Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show']);
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update']);
    Route::patch('/usuarios/{usuario}', [UsuarioController::class, 'update']);
    Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy']);
});
