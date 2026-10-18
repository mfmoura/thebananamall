<?php

namespace App\Http\Middleware;

use App\Services\DashboardSessaoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RequireDashboard
{
    public function __construct(private readonly DashboardSessaoService $sessao) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->sessao->usuario()) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Entre com uma conta administrativa.'], 401)
                : redirect('/dashboard/login');
        }

        Auth::shouldUse('dashboard');

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
