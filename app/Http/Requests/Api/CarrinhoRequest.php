<?php

namespace App\Http\Requests\Api;

use App\Models\Cliente;
use App\Models\Visitante;
use App\Services\CarrinhoIdentidadeService;
use Illuminate\Foundation\Http\FormRequest;

class CarrinhoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function dono(CarrinhoIdentidadeService $identidadeService): Cliente|Visitante
    {
        return $identidadeService->resolver($this);
    }
}
