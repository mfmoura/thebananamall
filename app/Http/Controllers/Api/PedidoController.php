<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CancelPedidoRequest;
use App\Http\Requests\Api\ListPedidosRequest;
use App\Http\Requests\Api\ShowPedidoRequest;
use App\Http\Requests\Api\StorePedidoRequest;
use App\Models\Pedido;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;

class PedidoController extends Controller
{
    public function index(ListPedidosRequest $request, PedidoService $pedidoService): JsonResponse
    {
        return response()->json($pedidoService->listar($request->cliente()));
    }

    public function store(StorePedidoRequest $request, PedidoService $pedidoService): JsonResponse
    {
        $dados = $request->validated();
        $pedido = $pedidoService->comprar(
            $request->cliente(),
            (int) $dados['forma_pagamento_id'],
            $dados['idempotency_key'],
        );

        return response()->json($pedido, 201);
    }

    public function show(ShowPedidoRequest $request, Pedido $pedido, PedidoService $pedidoService): JsonResponse
    {
        return response()->json($pedidoService->buscar($request->cliente(), $pedido));
    }

    public function cancelar(
        CancelPedidoRequest $request,
        Pedido $pedido,
        PedidoService $pedidoService,
    ): JsonResponse {
        if (! $pedidoService->cancelar($request->cliente(), $pedido)) {
            return response()->json(['message' => 'Este pedido não pode ser cancelado.'], 409);
        }

        return response()->json($pedido->refresh()->load(['formaPagamento', 'itens']));
    }
}
