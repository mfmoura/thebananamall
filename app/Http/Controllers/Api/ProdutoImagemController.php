<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteProdutoImagemRequest;
use App\Http\Requests\Api\StoreProdutoImagemRequest;
use App\Http\Requests\Api\UpdateProdutoImagemRequest;
use App\Http\Requests\Api\UploadProdutoImagemRequest;
use App\Models\Produto;
use App\Models\ProdutoImagem;
use App\Services\ProdutoImagemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProdutoImagemController extends Controller
{
    public function upload(UploadProdutoImagemRequest $request, Produto $produto, ProdutoImagemService $service): JsonResponse
    {
        $dados = $request->validated();

        return response()->json($service->enviarArquivo($produto, $dados['arquivo'], (bool) ($dados['principal'] ?? false)), 201);
    }

    public function store(
        StoreProdutoImagemRequest $request,
        Produto $produto,
        ProdutoImagemService $service,
    ): JsonResponse {
        return response()->json($service->criar($produto, $request->validated()), 201);
    }

    public function update(
        UpdateProdutoImagemRequest $request,
        Produto $produto,
        ProdutoImagem $imagem,
        ProdutoImagemService $service,
    ): JsonResponse {
        return response()->json($service->atualizar($produto, $imagem, $request->validated()));
    }

    public function destroy(
        DeleteProdutoImagemRequest $request,
        Produto $produto,
        ProdutoImagem $imagem,
        ProdutoImagemService $service,
    ): Response {
        $service->excluir($produto, $imagem);

        return response()->noContent();
    }
}
