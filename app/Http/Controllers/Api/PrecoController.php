<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeletePrecoRequest;
use App\Http\Requests\Api\ListPrecosRequest;
use App\Http\Requests\Api\StorePrecoRequest;
use App\Models\Preco;
use App\Models\Produto;
use App\Services\PrecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PrecoController extends Controller
{
    public function index(ListPrecosRequest $request, Produto $produto, PrecoService $service): JsonResponse
    {
        return response()->json($service->historico($produto));
    }

    public function store(StorePrecoRequest $request, Produto $produto, PrecoService $service): JsonResponse
    {
        return response()->json($service->criar($produto, (string) $request->validated('preco')), 201);
    }

    public function destroy(DeletePrecoRequest $request, Preco $preco, PrecoService $service): Response|JsonResponse
    {
        if (! $service->excluir($preco)) {
            return response()->json([
                'message' => 'O preço atual ou referenciado por um pedido não pode ser excluído.',
            ], 409);
        }

        return response()->noContent();
    }
}
