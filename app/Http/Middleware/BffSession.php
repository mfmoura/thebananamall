<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BffSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // O driver cookie colocaria dados da sessão no navegador.
        abort_if(config('session.driver') === 'cookie', 503, 'A sessão BFF requer armazenamento no servidor.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->setVary('Cookie', false);
        return $response;
    }
}
