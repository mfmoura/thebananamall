<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListProdutoMovimentacoesRequest;
use App\Http\Requests\Api\StoreProdutoMovimentacaoRequest;
use App\Http\Requests\Api\ShowProdutoEstoqueRequest;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Services\ProdutoMovimentacaoService;
use App\Services\ProdutoService;
use Illuminate\Http\JsonResponse;

class ProdutoMovimentacaoController extends Controller
{
    public function produto(Produto $produto, ProdutoService $service): JsonResponse
    {
        return response()->json($service->consultarLogistica($produto));
    }

    public function historico(\App\Http\Requests\Api\HistoricoEstoqueRequest $request, ProdutoService $service): JsonResponse
    {
        return response()->json($service->historicoEstoque($request->validated()));
    }

    public function index(ListProdutoMovimentacoesRequest $request, ProdutoService $service): JsonResponse
    {
        return response()->json($service->movimentacoes());
    }

    public function contarEstoque(
        ShowProdutoEstoqueRequest $request,
        Produto $produto,
        ProdutoMovimentacaoService $service,
    ): JsonResponse {
        return response()->json(['produto_id' => $produto->id] + $service->resumoEstoque($produto));
    }

    public function store(
        StoreProdutoMovimentacaoRequest $request,
        ProdutoMovimentacaoService $service,
    ): JsonResponse {
        return response()->json($service->registrarManual(
            $request->user(),
            $request->validated(),
        ), 201);
    }

    public function destroy(
        ListProdutoMovimentacoesRequest $request,
        ProdutoMovimentacao $movimentacao,
        ProdutoMovimentacaoService $service,
    ): JsonResponse {
        if (! $service->estornar($movimentacao, $request->user()->id)) {
            return response()->json(['message' => 'A movimentação já foi estornada ou não pode ser estornada.'], 409);
        }

        return response()->json(['message' => 'Movimentação estornada.']);
    }
}
