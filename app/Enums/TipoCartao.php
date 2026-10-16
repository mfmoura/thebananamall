<?php

namespace App\Enums;

enum TipoCartao: string
{
    case Credito = 'credito';
    case Debito = 'debito';
}
