<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\FormatApiResponse;
use App\Http\Responses\ApiResponse;
use App\Http\Middleware\RequirePerfil;
use App\Http\Middleware\RequirePaginaAdmin;
use App\Http\Middleware\EnsureUsuarioLiberado;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [FormatApiResponse::class]);
        $middleware->alias([
            'perfil' => RequirePerfil::class,
            'pagina-admin' => RequirePaginaAdmin::class,
            'scope' => CheckTokenForAnyScope::class,
            'scopes' => CheckToken::class,
            'conta-pronta' => EnsureUsuarioLiberado::class,
        ]);
        $middleware->web(append: [EnsureUsuarioLiberado::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => $request->is('api/*', 'bff/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*', 'bff/*')) {
                return null;
            }

            return ApiResponse::fromException($exception);
        });
    })->create();
