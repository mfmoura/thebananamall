<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Visitante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class CarrinhoIdentidadeService
{
    public function __construct(private readonly VisitanteService $visitanteService) {}

    public function resolver(Request $request): Cliente|Visitante
    {
        if ($request->bearerToken() !== null) {
            $usuario = Auth::guard('api')->user();

            if ($usuario === null) {
                throw new UnauthorizedHttpException('Bearer', 'Token OAuth2 inválido ou expirado.');
            }

            abort_unless($usuario->email_validado, 403, 'Confirme seu e-mail antes de usar o carrinho.');
            abort_if($usuario->must_change_password, 403, 'Troque sua senha antes de usar o carrinho.');

            $escopo = $request->isMethod('GET') ? 'cart:read' : 'cart:write';
            abort_unless($usuario->tokenCan($escopo), 403, 'O token OAuth2 não possui o escopo de carrinho necessário.');

            $cliente = $usuario->cliente;

            abort_if($cliente === null, 403, 'Somente um Cliente pode manter este carrinho.');

            return $cliente;
        }

        $tokenVisitante = $request->header('X-Visitante-Token');

        if (! is_string($tokenVisitante) || $tokenVisitante === '') {
            throw new UnauthorizedHttpException('Visitante', 'Inicialize um visitante e envie X-Visitante-Token.');
        }

        $visitante = $this->visitanteService->buscarPorToken($tokenVisitante);

        if ($visitante === null) {
            throw new UnauthorizedHttpException('Visitante', 'Token de visitante inválido ou expirado.');
        }

        $this->visitanteService->renovar($visitante);

        return $visitante;
    }
}
