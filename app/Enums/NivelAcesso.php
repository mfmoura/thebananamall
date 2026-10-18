<?php

namespace App\Enums;

enum NivelAcesso: string
{
    case Contabilidade = 'contabilidade';
    case Estoque = 'estoque';
    case Manutencao = 'manutencao';
    case Usuarios = 'usuarios';
}
