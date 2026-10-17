<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUsuarioLiberado
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('oauth/login', 'oauth/logout', 'oauth/csrf', 'bff/logout')) {
            return $next($request);
        }

        $usuario = $request->user();

        if ($usuario === null) {
            return $next($request);
        }

        if (! $usuario->email_validado) {
            return $this->bloquear(
                $request,
                'email_confirmation_required',
                'Confirme seu e-mail antes de acessar a conta.',
            );
        }

        if ($usuario->must_change_password) {
            return $this->bloquear(
                $request,
                'password_change_required',
                'Troque sua senha antes de acessar a conta.',
            );
        }

        if ($usuario->must_reauthenticate) {
            return $this->bloquear(
                $request,
                'reauthentication_required',
                'Entre novamente para continuar.',
            );
        }

        return $next($request);
    }

    private function bloquear(Request $request, string $codigo, string $mensagem): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['code' => $codigo, 'message' => $mensagem], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $destino = match ($codigo) {
            'email_confirmation_required' => '/email/pendente',
            'password_change_required' => '/senha-obrigatoria',
            default => '/login',
        };

        return redirect()->away(rtrim((string) config('services.frontend.url'), '/').$destino);
    }
}
