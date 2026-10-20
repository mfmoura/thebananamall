<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OAuthSessionController;
use App\Http\Controllers\Web\FirstLoginPasswordController;
use Illuminate\Http\Request;
use App\Http\Controllers\Web\BffController;

Route::prefix('dashboard')->withoutMiddleware(\App\Http\Middleware\EnsureUsuarioLiberado::class)->group(function (): void {
    Route::view('/login', 'dashboard')->name('dashboard.login');
    Route::view('/email/verify', 'dashboard');
    Route::view('/email/pendente', 'dashboard');
    Route::view('/senha-obrigatoria', 'dashboard');
    Route::post('/email/confirmar', [\App\Http\Controllers\Api\EmailVerificationController::class, 'verifyDashboard'])->middleware('throttle:10,1');
    Route::post('/login', [\App\Http\Controllers\Web\DashboardSessaoController::class, 'store'])->middleware('throttle:6,1')->block(30, 10);
    Route::post('/logout', [\App\Http\Controllers\Web\DashboardSessaoController::class, 'destroy'])->block(30, 10);
    Route::get('/session', [\App\Http\Controllers\Web\DashboardSessaoController::class, 'show'])->middleware(\App\Http\Middleware\RequireDashboard::class);
    Route::view('/', 'dashboard')->middleware(\App\Http\Middleware\RequireDashboard::class);
    Route::middleware([\App\Http\Middleware\RequireDashboard::class, \App\Http\Middleware\BffSession::class])->group(function (): void {
        Route::post('/authorize', [\App\Http\Controllers\Web\DashboardSessaoController::class, 'authorize'])->block(30, 10);
        Route::get('/oauth/authorize', [\App\Http\Controllers\Web\DashboardAuthorizationController::class, 'authorize'])->block(30, 10);
        Route::get('/callback', [\App\Http\Controllers\Web\DashboardSessaoController::class, 'callback'])->block(30, 10);
        Route::match(['GET', 'POST', 'PATCH', 'DELETE'], '/api/{path}', \App\Http\Controllers\Web\DashboardProxyController::class)
            ->where('path', '[a-z0-9/-]+')->block(60, 15);
        Route::view('/logistica', 'dashboard')->middleware('pagina-admin:estoque,manutencao,contabilidade');
        Route::view('/logistica/{produto}', 'dashboard')->whereNumber('produto')->middleware('pagina-admin:estoque,manutencao,contabilidade');
        Route::view('/produtos', 'dashboard')->middleware('pagina-admin:estoque');
        Route::view('/produtos/novo', 'dashboard')->middleware('pagina-admin:estoque');
        Route::view('/produtos/{produto}/editar', 'dashboard')->whereNumber('produto')->middleware('pagina-admin:estoque');
        Route::view('/expedicao', 'dashboard')->middleware('pagina-admin:pedidos');
        Route::view('/expedicao/{pedido}', 'dashboard')->whereNumber('pedido')->middleware('pagina-admin:pedidos');
        Route::view('/pedidos', 'dashboard')->middleware('pagina-admin:pedidos');
        Route::view('/pedidos/{pedido}', 'dashboard')->whereNumber('pedido')->middleware('pagina-admin:pedidos');
        Route::view('/usuarios', 'dashboard')->middleware('pagina-admin:usuarios,manutencao,estoque,contabilidade,pedidos');
        Route::view('/usuarios/novo', 'dashboard')->middleware('pagina-admin:usuarios,manutencao,estoque,contabilidade,pedidos');
        Route::view('/usuarios/{usuario}/visualizar', 'dashboard')->whereNumber('usuario')->middleware('pagina-admin:usuarios,manutencao,estoque,contabilidade,pedidos');
        Route::view('/usuarios/{usuario}/editar', 'dashboard')->whereNumber('usuario')->middleware('pagina-admin:usuarios,manutencao,estoque,contabilidade,pedidos');
    });
});

Route::prefix('bff')->middleware(\App\Http\Middleware\BffSession::class)->group(function (): void {
    Route::get('/session', [BffController::class, 'session'])->block(30, 10);
    Route::post('/authorize', [BffController::class, 'authorize'])->middleware('auth:web')->block(30, 10);
    Route::get('/callback', [BffController::class, 'callback'])->middleware('auth:web')->block(30, 10);
    Route::post('/logout', [BffController::class, 'logout'])->block(30, 10);
    Route::post('/visitante', [BffController::class, 'visitante'])->middleware('throttle:20,1')->block(30, 10);
    Route::match(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], '/api/{path}', [BffController::class, 'proxy'])
        ->where('path', '[a-z0-9/-]+')->block(60, 15);
});

Route::get('/', function () {
    return view('hello');
});

Route::view('/login', 'hello')->name('login');

Route::get('/oauth/csrf', function (Request $request) {
    return response()->json(['csrf_token' => $request->session()->token()]);
});

Route::post('/oauth/login', [OAuthSessionController::class, 'store'])->middleware('throttle:6,1');
Route::post('/oauth/first-login/password', [FirstLoginPasswordController::class, 'update'])
    ->middleware('throttle:6,1');

Route::post('/oauth/logout', [OAuthSessionController::class, 'destroy']);

// Somente rotas de apresentação; APIs, Passport e arquivos não entram no fallback.
Route::view('/{pagina}', 'hello')->where('pagina', '(?:produtos(?:/[0-9]+)?|carrinho|checkout(?:/pagamento/[0-9]+)?|conta|cadastro|pedidos(?:/[0-9]+)?|oauth/callback|email/(?:verify|pendente)|senha/(?:recuperar|redefinir)|password/reset|senha-obrigatoria)');
