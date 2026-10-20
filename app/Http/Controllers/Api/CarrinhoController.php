<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CarrinhoRequest;
use App\Http\Requests\Api\DeleteCarrinhoItemRequest;
use App\Http\Requests\Api\MergeCarrinhoRequest;
use App\Http\Requests\Api\StoreCarrinhoItemRequest;
use App\Http\Requests\Api\UpdateCarrinhoItemRequest;
use App\Http\Requests\Api\UpdateCarrinhoRequest;
use App\Services\CarrinhoIdentidadeService;
use App\Services\CarrinhoService;
use App\Services\VisitanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CarrinhoController extends Controller
{
    public function __construct(
        private readonly CarrinhoService $carrinhoService,
        private readonly CarrinhoIdentidadeService $identidadeService,
        private readonly VisitanteService $visitanteService,
    ) {}

    public function show(CarrinhoRequest $request): JsonResponse
    {
        $carrinho = $this->carrinhoService->consultar($request->dono($this->identidadeService));

        if ($carrinho === null) {
            return response()->json(['message' => 'Carrinho não encontrado.'], 404);
        }

        return response()->json($carrinho);
    }

    public function store(CarrinhoRequest $request): JsonResponse
    {
        return response()->json($this->carrinhoService->criar($request->dono($this->identidadeService)));
    }

    public function update(UpdateCarrinhoRequest $request): JsonResponse
    {
        $carrinho = $this->carrinhoService->atualizar(
            $request->dono($this->identidadeService),
            $request->validated('itens'),
        );

        if ($carrinho === null) {
            return response()->json(['message' => 'Carrinho não encontrado.'], 404);
        }

        return response()->json($carrinho);
    }

    public function destroy(CarrinhoRequest $request): Response|JsonResponse
    {
        if (! $this->carrinhoService->excluir($request->dono($this->identidadeService))) {
            return response()->json(['message' => 'Carrinho não encontrado.'], 404);
        }

        return response()->noContent();
    }

    public function adicionarItem(StoreCarrinhoItemRequest $request): JsonResponse
    {
        $dados = $request->validated();

        return response()->json($this->carrinhoService->adicionarProduto(
            $request->dono($this->identidadeService),
            (int) $dados['codigo'],
            (float) ($dados['quantidade'] ?? 1),
            $dados['tamanho'] ?? null,
        ));
    }

    public function atualizarItem(UpdateCarrinhoItemRequest $request, int $codigo): JsonResponse
    {
        $carrinho = $this->carrinhoService->atualizarItem(
            $request->dono($this->identidadeService),
            $codigo,
            (float) $request->validated('quantidade'),
            $request->validated('tamanho'),
        );

        if ($carrinho === null) {
            return response()->json(['message' => 'Item não encontrado no carrinho.'], 404);
        }

        return response()->json($carrinho);
    }

    public function excluirItem(DeleteCarrinhoItemRequest $request, int $codigo): Response|JsonResponse
    {
        if (! $this->carrinhoService->excluirItem($request->dono($this->identidadeService), $codigo, $request->validated('tamanho'))) {
            return response()->json(['message' => 'Item não encontrado no carrinho.'], 404);
        }

        return response()->noContent();
    }

    public function merge(MergeCarrinhoRequest $request): JsonResponse
    {
        $visitante = $this->visitanteService->buscarPorToken($request->validated('visitante_token'));

        abort_if($visitante === null, 401, 'Token de visitante inválido ou expirado.');

        return response()->json($this->carrinhoService->mesclar($request->cliente(), $visitante));
    }
}
