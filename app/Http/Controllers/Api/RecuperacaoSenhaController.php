<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RedefinirSenhaRequest;
use App\Http\Requests\Api\SolicitarRecuperacaoSenhaRequest;
use App\Services\RecuperacaoSenhaService;
use Illuminate\Http\JsonResponse;

class RecuperacaoSenhaController extends Controller
{
    public function solicitar(
        SolicitarRecuperacaoSenhaRequest $request,
        RecuperacaoSenhaService $service,
    ): JsonResponse {
        $service->solicitar($request->validated('email'));

        return response()->json([
            'message' => 'Se a conta existir, enviaremos um link para redefinir a senha.',
        ], 202);
    }

    public function redefinir(
        RedefinirSenhaRequest $request,
        RecuperacaoSenhaService $service,
    ): JsonResponse {
        $service->redefinir($request->validated('token'), $request->validated('password'));

        return response()->json([
            'message' => 'Senha redefinida. Entre novamente para continuar.',
            'requires_login' => true,
        ]);
    }
}
