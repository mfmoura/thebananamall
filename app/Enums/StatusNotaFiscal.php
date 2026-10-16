<?php

namespace App\Enums;

enum StatusNotaFiscal: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Autorizada = 'autorizada';
    case Rejeitada = 'rejeitada';
    case Cancelada = 'cancelada';
    case Erro = 'erro';
}
