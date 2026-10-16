<?php

namespace App\Enums;

enum StatusPedido: string
{
    case AguardandoPagamento = 'aguardando_pagamento';
    case Pago = 'pago';
    case EmProcessamento = 'em_processamento';
    case Enviado = 'enviado';
    case Entregue = 'entregue';
    case Cancelado = 'cancelado';
    case Falhou = 'falhou';
}
