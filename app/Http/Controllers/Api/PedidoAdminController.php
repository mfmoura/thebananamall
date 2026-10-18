<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PedidoAdminRequest;
use App\Http\Requests\Api\ListPedidosAdminRequest;
use App\Models\Pedido;
use App\Services\PedidoAdminService;
use App\Services\PedidoFiscalService;
use App\Services\PedidoService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PedidoAdminController extends Controller
{
    public function index(ListPedidosAdminRequest $request, PedidoAdminService $service): JsonResponse
    {
        return response()->json($service->listar($request->validated()));
    }

    public function show(PedidoAdminRequest $request, Pedido $pedido, PedidoAdminService $service): JsonResponse
    {
        return response()->json($service->buscar($pedido));
    }

    public function cancelar(PedidoAdminRequest $request, Pedido $pedido, PedidoService $service, PedidoAdminService $consulta): JsonResponse
    {
        abort_unless($service->cancelar($pedido->cliente, $pedido), 409, 'Este pedido não pode ser cancelado. Cobranças existentes exigem cancelamento no provedor.');

        return response()->json($consulta->buscar($pedido->refresh()));
    }

    public function danfe(PedidoAdminRequest $request, Pedido $pedido, PedidoFiscalService $service): StreamedResponse
    {
        return $service->baixarDanfe($pedido);
    }

    public function etiqueta(PedidoAdminRequest $request, Pedido $pedido, PedidoAdminService $service): JsonResponse
    {
        return response()->json($service->etiqueta($pedido));
    }
}
