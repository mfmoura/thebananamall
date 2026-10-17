<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteAdministradorRequest;
use App\Http\Requests\Api\ShowAdministradorRequest;
use App\Http\Requests\Api\StoreAdministradorRequest;
use App\Http\Requests\Api\UpdateAdministradorRequest;
use App\Models\Administrador;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdministradorController extends Controller
{
    public function store(StoreAdministradorRequest $request, UsuarioService $usuarioService): JsonResponse
    {
        return response()->json($usuarioService->criarAdministrador($request->validated()), 201);
    }

    public function show(
        ShowAdministradorRequest $request,
        Administrador $administrador,
        UsuarioService $usuarioService,
    ): JsonResponse {
        return response()->json($usuarioService->consultarAdministrador($administrador));
    }

    public function update(
        UpdateAdministradorRequest $request,
        Administrador $administrador,
        UsuarioService $usuarioService,
    ): JsonResponse {
        return response()->json($usuarioService->atualizarAdministrador($administrador, $request->validated()));
    }

    public function destroy(
        DeleteAdministradorRequest $request,
        Administrador $administrador,
        UsuarioService $usuarioService,
    ): Response {
        $usuarioService->excluirAdministrador($administrador);

        return response()->noContent();
    }
}
