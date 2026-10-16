<?php

namespace App\Enums;

enum StatusClienteCartao: string
{
    case Ativo = 'ativo';
    case Inativo = 'inativo';
    case Expirado = 'expirado';
    case Bloqueado = 'bloqueado';
}
