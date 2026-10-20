<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CriarPagamentoRequest;
use App\Http\Requests\Api\MercadoPagoWebhookRequest;
use App\Http\Requests\Api\ShowPedidoRequest;
use App\Models\Pedido;
use App\Services\MercadoPagoService;
use App\Services\MercadoPagoWebhookService;
use Illuminate\Http\JsonResponse;

class PagamentoController extends Controller
{
    public function configuracao(MercadoPagoService $service): JsonResponse
    {
        return response()->json($service->configuracaoPublica())->header('Cache-Control', 'no-store, private');
    }

    public function store(CriarPagamentoRequest $request, Pedido $pedido, MercadoPagoService $service): JsonResponse
    {
        return response()->json($service->criar($pedido, $request->validated()), 202)->header('Cache-Control', 'no-store, private');
    }

    public function show(ShowPedidoRequest $request, Pedido $pedido, MercadoPagoService $service): JsonResponse
    {
        return response()->json($service->consultar($pedido))->header('Cache-Control', 'no-store, private');
    }

    public function webhook(MercadoPagoWebhookRequest $request, MercadoPagoWebhookService $service): JsonResponse
    {
        $request->validated();
        $service->receber($request->orderId(), trim((string) $request->header('x-request-id')), trim((string) $request->header('x-signature')));

        return response()->json(['recebido' => true]);
    }
}
