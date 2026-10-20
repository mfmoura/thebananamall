<?php

namespace App\Services;

use App\Models\Produto;
use Illuminate\Validation\ValidationException;

class GradeProdutoService
{
    public function validarTamanho(Produto $produto, ?string $tamanho, float $quantidade): void
    {
        $grade = $produto->grade ?? [];
        if (($grade === [] && $tamanho !== null) || ($grade !== [] && ! in_array($tamanho, $grade, true))) {
            throw ValidationException::withMessages(['tamanho' => 'Selecione um tamanho da grade do produto.']);
        }
        if ($grade !== [] && floor($quantidade) !== $quantidade) {
            throw ValidationException::withMessages(['quantidade' => 'Produtos com grade exigem quantidade inteira.']);
        }
    }

    public function validarAlteracao(Produto $produto, array $grade): void
    {
        $anterior = $produto->grade ?? [];
        if ($grade === $anterior) return;
        // A identidade de tamanhos com histórico nunca pode ser reinterpretada.
        if ($anterior === [] && $produto->movimentacoes()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['grade' => 'Cadastre um novo produto com grade para preservar o estoque e o histórico sem tamanho.']);
        }
        if (array_diff($anterior, $grade) !== []) {
            throw ValidationException::withMessages(['grade' => 'Preserve os tamanhos existentes; é permitido acrescentar novos tamanhos.']);
        }
    }
}
