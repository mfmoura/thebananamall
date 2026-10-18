<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CategoriaService;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    public function index(CategoriaService $service): JsonResponse
    {
        return response()->json($service->listar());
    }
}
