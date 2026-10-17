<?php

namespace App\Services;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Despacha exclusivamente rotas locais, preservando os middlewares da API. */
class BffApiService
{
    public function __construct(private readonly Router $router, private readonly ExceptionHandler $exceptions) {}

    public function executar(Request $externa, string $path, string $method, array $body = [], array $headers = []): Response
    {
        $original = app('request');
        $originalRoute = $externa->route();
        $guard = config('auth.defaults.guard');
        $request = Request::create(rtrim(config('app.url'), '/').$path, $method, [], [], [], [
            'REMOTE_ADDR' => $externa->ip(), 'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body, JSON_THROW_ON_ERROR));
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }
        $request->setUserResolver(fn ($name = null) => Auth::guard($name)->user());
        try {
            app()->instance('request', $request);
            app('url')->setRequest($request);
            Auth::forgetGuards();
            return $this->router->dispatch($request);
        } catch (Throwable $exception) {
            return $this->exceptions->render($request, $exception);
        } finally {
            app()->instance('request', $original);
            app('url')->setRequest($original);
            Auth::forgetGuards();
            Auth::shouldUse($guard);
            if ($originalRoute) app()->instance(\Illuminate\Routing\Route::class, $originalRoute);
        }
    }
}
