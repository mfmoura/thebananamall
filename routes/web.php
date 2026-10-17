<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OAuthSessionController;
use App\Http\Controllers\Web\FirstLoginPasswordController;
use Illuminate\Http\Request;
use App\Http\Controllers\Web\BffController;

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
Route::view('/{pagina}', 'hello')->where('pagina', '(?:produtos(?:/[0-9]+)?|carrinho|checkout|conta|cadastro|pedidos(?:/[0-9]+)?|oauth/callback|email/(?:verify|pendente)|senha/(?:recuperar|redefinir)|password/reset|senha-obrigatoria)');
