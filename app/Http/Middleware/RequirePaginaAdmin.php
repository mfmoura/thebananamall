<?php

namespace App\Http\Middleware;

use App\Enums\NivelAcesso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePaginaAdmin
{
    public function handle(Request $request, Closure $next, string ...$paginas): Response
    {
        $administrador = $request->user()?->administrador;
        $paginasPermitidas = collect($paginas)
            ->flatMap(static fn (string $pagina): array => explode(',', $pagina))
            ->map(static fn (string $pagina): ?NivelAcesso => NivelAcesso::tryFrom($pagina))
            ->filter();

        abort_unless(
            $paginasPermitidas->isNotEmpty()
                && $administrador !== null
                && $paginasPermitidas->contains(fn (NivelAcesso $pagina): bool => $administrador->acessaPagina($pagina)),
            403,
            'Seu perfil administrativo não tem acesso a esta página.',
        );

        return $next($request);
    }
}
