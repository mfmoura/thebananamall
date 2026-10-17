<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteClienteRequest;
use App\Http\Requests\Api\ShowClienteRequest;
use App\Http\Requests\Api\StoreClienteRequest;
use App\Http\Requests\Api\UpdateClienteRequest;
use App\Models\Cliente;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClienteController extends Controller
{
    public function store(StoreClienteRequest $request, UsuarioService $usuarioService): JsonResponse
    {
        return response()->json($usuarioService->criarCliente($request->validated()), 201);
    }

    public function show(ShowClienteRequest $request, Cliente $cliente, UsuarioService $usuarioService): JsonResponse
    {
        return response()->json($usuarioService->consultarCliente($cliente));
    }

    public function update(
        UpdateClienteRequest $request,
        Cliente $cliente,
        UsuarioService $usuarioService,
    ): JsonResponse {
        return response()->json($usuarioService->atualizarCliente($cliente, $request->validated()));
    }

    public function destroy(
        DeleteClienteRequest $request,
        Cliente $cliente,
        UsuarioService $usuarioService,
    ): Response {
        $usuarioService->excluirCliente($cliente);

        return response()->noContent();
    }
}
