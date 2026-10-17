<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteUsuarioRequest;
use App\Http\Requests\Api\ShowUsuarioRequest;
use App\Http\Requests\Api\UpdateEmailRequest;
use App\Http\Requests\Api\UpdateUsuarioRequest;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UsuarioController extends Controller
{
    public function show(
        ShowUsuarioRequest $request,
        Usuario $usuario,
        UsuarioService $usuarioService,
    ): JsonResponse {
        return response()->json($usuarioService->consultar($usuario));
    }

    public function update(
        UpdateUsuarioRequest $request,
        Usuario $usuario,
        UsuarioService $usuarioService,
    ): JsonResponse {
        return response()->json($usuarioService->atualizar($usuario, $request->validated()));
    }

    public function updateEmail(
        UpdateEmailRequest $request,
        Usuario $usuario,
        UsuarioService $usuarioService,
    ): JsonResponse {
        $usuarioService->solicitarTrocaEmail($usuario, $request->validated('email'));

        return response()->json([
            'message' => 'Enviamos um link ao novo endereço. Confirme-o e escolha uma nova senha em até '.
                (int) config('services.email_verification.ttl', 300).' segundos.',
        ], 202);
    }

    public function destroy(
        DeleteUsuarioRequest $request,
        Usuario $usuario,
        UsuarioService $usuarioService,
    ): Response {
        $usuarioService->excluir($usuario);

        return response()->noContent();
    }
}
