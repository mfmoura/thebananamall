<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FormaPagamentoService;
use Illuminate\Http\JsonResponse;

class FormaPagamentoController extends Controller
{
    public function index(FormaPagamentoService $service): JsonResponse
    {
        return response()->json($service->listarAtivas());
    }
}
