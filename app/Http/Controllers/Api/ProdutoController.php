<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteProdutoRequest;
use App\Http\Requests\Api\StoreProdutoRequest;
use App\Http\Requests\Api\UpdateProdutoRequest;
use App\Models\Produto;
use App\Services\ProdutoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProdutoController extends Controller
{
    public function index(\App\Http\Requests\Api\ListProdutosRequest $request, ProdutoService $service): JsonResponse
    {
        $dados = $request->validated();

        return response()->json($service->listar(
            isset($dados['per_page']) ? (int) $dados['per_page'] : null,
            (int) ($dados['page'] ?? 1),
            $dados,
        ));
    }

    public function show(Produto $produto, ProdutoService $service): JsonResponse
    {
        return response()->json($service->consultar($produto));
    }

    public function store(StoreProdutoRequest $request, ProdutoService $service): JsonResponse
    {
        return response()->json($service->criar($request->validated()), 201);
    }

    public function update(
        UpdateProdutoRequest $request,
        Produto $produto,
        ProdutoService $service,
    ): JsonResponse {
        return response()->json($service->atualizar($produto, $request->validated()));
    }

    public function destroy(DeleteProdutoRequest $request, Produto $produto, ProdutoService $service): Response
    {
        $service->excluir($produto);

        return response()->noContent();
    }
}
