<?php

namespace App\Enums;

enum NaturezaOperacaoNotaFiscal: string
{
    case Compra = 'compra';
    case Remessa = 'remessa';
    case Devolucao = 'devolucao';
    case Doacao = 'doacao';
    case Venda = 'venda';
    case Perda = 'perda';

    public function tipoOperacao(): TipoOperacaoNotaFiscal
    {
        return match ($this) {
            self::Compra, self::Remessa, self::Devolucao => TipoOperacaoNotaFiscal::Entrada,
            self::Doacao, self::Venda, self::Perda => TipoOperacaoNotaFiscal::Saida,
        };
    }
}
