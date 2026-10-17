<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BaixarNotaFiscalRequest;
use App\Http\Requests\Api\ConsultarNotaFiscalRequest;
use App\Http\Requests\Api\EmitirNotaFiscalRequest;
use App\Http\Requests\Api\StoreNotaFiscalRequest;
use App\Models\NotaFiscal;
use App\Services\ContoraNotaFiscalService;
use App\Services\NotaFiscalService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NotaFiscalController extends Controller
{
    public function emitir(EmitirNotaFiscalRequest $request, ContoraNotaFiscalService $service): JsonResponse
    {
        return response()->json($service->emitir($request->user(), $request->validated()), 202);
    }

    public function show(ConsultarNotaFiscalRequest $request, NotaFiscal $notaFiscal): JsonResponse
    {
        return response()->json($notaFiscal->load('itens'));
    }

    public function sincronizar(ConsultarNotaFiscalRequest $request, NotaFiscal $notaFiscal, ContoraNotaFiscalService $service): JsonResponse
    {
        return response()->json($service->sincronizar($notaFiscal));
    }

    public function xml(ConsultarNotaFiscalRequest $request, NotaFiscal $notaFiscal, ContoraNotaFiscalService $service): StreamedResponse
    {
        return $service->baixar($notaFiscal, 'xml');
    }

    public function danfe(BaixarNotaFiscalRequest $request, NotaFiscal $notaFiscal, ContoraNotaFiscalService $service): StreamedResponse
    {
        return $service->baixar($notaFiscal, 'danfe');
    }

    public function store(StoreNotaFiscalRequest $request, NotaFiscalService $service): JsonResponse
    {
        return response()->json($service->registrar($request->user(), $request->validated()), 201);
    }
}
