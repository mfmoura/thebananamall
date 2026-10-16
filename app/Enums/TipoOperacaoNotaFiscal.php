<?php

namespace App\Enums;

enum TipoOperacaoNotaFiscal: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
}
