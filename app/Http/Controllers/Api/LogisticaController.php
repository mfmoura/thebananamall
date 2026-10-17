<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteLogisticaRequest;
use App\Http\Requests\Api\ShowLogisticaRequest;
use App\Http\Requests\Api\StoreLogisticaRequest;
use App\Http\Requests\Api\UpdateLogisticaRequest;
use App\Models\Logistica;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LogisticaController extends Controller
{
    public function store(StoreLogisticaRequest $request, UsuarioService $usuarioService): JsonResponse
    {
        return response()->json($usuarioService->criarLogistica($request->validated()), 201);
    }

    public function show(ShowLogisticaRequest $request, Logistica $logistica, UsuarioService $usuarioService): JsonResponse
    {
        return response()->json($usuarioService->consultarLogistica($logistica));
    }

    public function update(
        UpdateLogisticaRequest $request,
        Logistica $logistica,
        UsuarioService $usuarioService,
    ): JsonResponse {
        return response()->json($usuarioService->atualizarLogistica($logistica, $request->validated()));
    }

    public function destroy(
        DeleteLogisticaRequest $request,
        Logistica $logistica,
        UsuarioService $usuarioService,
    ): Response {
        $usuarioService->excluirLogistica($logistica);

        return response()->noContent();
    }
}
