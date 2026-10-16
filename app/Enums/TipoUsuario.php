<?php

namespace App\Enums;

enum TipoUsuario: string
{
    case Cliente = 'cliente';
    case Administrador = 'administrador';
    case Logistica = 'logistica';
}
