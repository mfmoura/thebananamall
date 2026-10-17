<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateEnderecoRequest;
use App\Http\Requests\Api\DeleteEnderecoRequest;
use App\Http\Requests\Api\ShowEnderecoRequest;
use App\Http\Requests\Api\UpdateEnderecoRequest;
use App\Models\Endereco;
use App\Services\EnderecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class EnderecoController extends Controller
{
    public function consultarCep(\App\Http\Requests\Api\ConsultarCepRequest $request, \App\Services\ViaCepService $viaCep): JsonResponse
    {
        $dados = $viaCep->consultar($request->validated('cep'));

        return response()->json([
            'cep' => $dados['cep'], 'logradouro' => $dados['logradouro'] ?? '',
            'cidade' => $dados['localidade'], 'estado' => $dados['uf'], 'pais' => 'BR',
        ]);
    }
    public function store(CreateEnderecoRequest $request, EnderecoService $enderecoService): JsonResponse
    {
        return response()->json($enderecoService->criar($request->user(), $request->validated()), 201);
    }

    public function show(ShowEnderecoRequest $request, Endereco $endereco, EnderecoService $enderecoService): JsonResponse
    {
        return response()->json($enderecoService->consultar($endereco));
    }

    public function update(
        UpdateEnderecoRequest $request,
        Endereco $endereco,
        EnderecoService $enderecoService,
    ): JsonResponse {
        return response()->json($enderecoService->atualizar($endereco, $request->validated()));
    }

    public function destroy(
        DeleteEnderecoRequest $request,
        Endereco $endereco,
        EnderecoService $enderecoService,
    ): Response {
        $enderecoService->excluir($endereco);

        return response()->noContent();
    }
}
