<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListProdutosAdminRequest;
use App\Models\Produto;
use App\Services\ProdutoService;

class ProdutoAdminController extends Controller
{
    public function index(ListProdutosAdminRequest $request, ProdutoService $service)
    {
        return response()->json($service->listarAdministracao($request->validated()));
    }

    public function show(Produto $produto, ProdutoService $service)
    {
        return response()->json($service->consultarAdministracao($produto));
    }
}
