<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteUsuarioRequest;
use App\Http\Requests\Api\ShowUsuarioRequest;
use App\Http\Requests\Api\StoreUsuarioRequest;
use App\Http\Requests\Api\UpdateUsuarioRequest;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class UsuarioController extends Controller
{
    public function store(StoreUsuarioRequest $request, UsuarioService $usuarioService): JsonResponse
    {
        return response()->json($usuarioService->criar($request->validated()), 201);
    }

    public function storeAsAdministrator(
        StoreUsuarioRequest $request,
        UsuarioService $usuarioService,
    ): JsonResponse {
        return response()->json($usuarioService->criar($request->validated()), 201);
    }

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

    public function destroy(
        DeleteUsuarioRequest $request,
        Usuario $usuario,
        UsuarioService $usuarioService,
    ): Response {
        $usuarioService->excluir($usuario);

        return response()->noContent();
    }
}
