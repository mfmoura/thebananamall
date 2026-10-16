<?php

namespace App\Enums;

enum DirecaoMovimentacao: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';

    public function oposta(): self
    {
        return $this === self::Entrada ? self::Saida : self::Entrada;
    }
}
