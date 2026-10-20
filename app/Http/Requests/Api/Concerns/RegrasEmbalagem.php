<?php

namespace App\Http\Requests\Api\Concerns;

trait RegrasEmbalagem
{
    protected function regrasEmbalagem(): array
    {
        $campos = ['peso_gramas', 'altura_cm', 'largura_cm', 'comprimento_cm'];
        $regras = ['embalagem_estimada' => ['sometimes', 'boolean']];
        foreach ($campos as $campo) {
            $regras[$campo] = [
                'required_with:'.implode(',', array_diff($campos, [$campo])), 'nullable',
                $campo === 'peso_gramas' ? 'integer' : 'numeric', 'gt:0',
                $campo === 'peso_gramas' ? 'max:30000' : 'max:100',
            ];
            if ($campo !== 'peso_gramas') {
                $regras[$campo][] = 'decimal:0,2';
            }
        }

        return $regras;
    }
}
