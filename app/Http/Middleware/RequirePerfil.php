<?php

namespace App\Http\Middleware;

use App\Enums\TipoUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePerfil
{
    public function handle(Request $request, Closure $next, string $perfil): Response
    {
        $usuario = $request->user();

        $autorizado = match ($perfil) {
            TipoUsuario::Cliente->value => $usuario?->cliente()->exists() === true,
            TipoUsuario::Administrador->value => $usuario?->administrador()->exists() === true,
            TipoUsuario::Logistica->value => $usuario?->logistica()->exists() === true,
            default => false,
        };

        abort_unless($autorizado, 403, 'Este perfil não tem acesso a esta operação.');

        return $next($request);
    }
}
