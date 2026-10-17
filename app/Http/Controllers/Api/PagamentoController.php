<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CriarPagamentoRequest;
use App\Http\Requests\Api\MercadoPagoWebhookRequest;
use App\Http\Requests\Api\ShowPedidoRequest;
use App\Models\Pedido;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;

class PagamentoController extends Controller
{
    public function store(CriarPagamentoRequest $request, Pedido $pedido, MercadoPagoService $service): JsonResponse
    {
        return response()->json($service->criar($pedido, $request->validated()), 201);
    }

    public function show(ShowPedidoRequest $request, Pedido $pedido, MercadoPagoService $service): JsonResponse
    {
        return response()->json($service->consultar($pedido));
    }

    public function webhook(MercadoPagoWebhookRequest $request, MercadoPagoService $service): JsonResponse
    {
        $service->sincronizar($request->orderId());

        return response()->json(['recebido' => true]);
    }
}
