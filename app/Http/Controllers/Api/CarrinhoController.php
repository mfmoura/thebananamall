<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CarrinhoRequest;
use App\Http\Requests\Api\StoreCarrinhoItemRequest;
use App\Http\Requests\Api\UpdateCarrinhoRequest;
use App\Http\Requests\Api\UpdateCarrinhoItemRequest;
use App\Services\CarrinhoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CarrinhoController extends Controller
{
    public function show(CarrinhoRequest $request, CarrinhoService $carrinhoService): JsonResponse
    {
        $carrinho = $carrinhoService->consultar($request->cliente());

        if ($carrinho === null) {
            return response()->json(['message' => 'Carrinho nao encontrado.'], 404);
        }

        return response()->json($carrinho);
    }

    public function store(CarrinhoRequest $request, CarrinhoService $carrinhoService): JsonResponse
    {
        return response()->json($carrinhoService->criar($request->cliente()), 200);
    }

    public function update(
        UpdateCarrinhoRequest $request,
        CarrinhoService $carrinhoService,
    ): JsonResponse {
        $carrinho = $carrinhoService->atualizar(
            $request->cliente(),
            $request->validated('itens'),
        );

        if ($carrinho === null) {
            return response()->json(['message' => 'Carrinho nao encontrado.'], 404);
        }

        return response()->json($carrinho);
    }

    public function destroy(CarrinhoRequest $request, CarrinhoService $carrinhoService): Response|JsonResponse
    {
        if (! $carrinhoService->excluir($request->cliente())) {
            return response()->json(['message' => 'Carrinho nao encontrado.'], 404);
        }

        return response()->noContent();
    }

    public function adicionarItem(
        StoreCarrinhoItemRequest $request,
        CarrinhoService $carrinhoService,
    ): JsonResponse {
        $dados = $request->validated();

        return response()->json($carrinhoService->adicionarProduto(
            $request->cliente(),
            (int) $dados['codigo'],
            (float) ($dados['quantidade'] ?? 1),
        ));
    }

    public function atualizarItem(
        UpdateCarrinhoItemRequest $request,
        int $codigo,
        CarrinhoService $carrinhoService,
    ): JsonResponse {
        $carrinho = $carrinhoService->atualizarItem(
            $request->cliente(),
            $codigo,
            (float) $request->validated('quantidade'),
        );

        if ($carrinho === null) {
            return response()->json(['message' => 'Item nao encontrado no carrinho.'], 404);
        }

        return response()->json($carrinho);
    }

    public function excluirItem(
        CarrinhoRequest $request,
        int $codigo,
        CarrinhoService $carrinhoService,
    ): Response|JsonResponse {
        if (! $carrinhoService->excluirItem($request->cliente(), $codigo)) {
            return response()->json(['message' => 'Item nao encontrado no carrinho.'], 404);
        }

        return response()->noContent();
    }
}
