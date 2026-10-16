<?php

namespace App\Enums;

enum NivelAcesso: string
{
    case Administrador = 'administrador';
    case SuperAdministrador = 'super_administrador';
}
