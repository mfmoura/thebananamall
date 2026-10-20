<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeleteCategoriaRequest;
use App\Http\Requests\Api\StoreCategoriaRequest;
use App\Http\Requests\Api\UpdateCategoriaRequest;
use App\Models\Categoria;
use App\Services\CategoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CategoriaController extends Controller
{
    public function store(StoreCategoriaRequest $request, CategoriaService $service): JsonResponse
    {
        return response()->json($service->criar($request->validated()), 201);
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria, CategoriaService $service): JsonResponse
    {
        return response()->json($service->atualizar($categoria, $request->validated()));
    }

    public function destroy(DeleteCategoriaRequest $request, Categoria $categoria, CategoriaService $service): Response
    {
        $service->excluir($categoria);

        return response()->noContent();
    }

    public function index(CategoriaService $service): JsonResponse
    {
        return response()->json($service->listar());
    }
}
