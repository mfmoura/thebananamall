<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VisitanteService;
use Illuminate\Http\JsonResponse;

class VisitanteController extends Controller
{
    public function store(VisitanteService $service): JsonResponse
    {
        return response()->json($service->criar(), 201);
    }
}
