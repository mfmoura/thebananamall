<?php

return [
    'token' => env('HUB_DESENVOLVEDOR_TOKEN'),
    'cep_origem' => env('FRETE_CEP_ORIGEM', '03044000'),
    'validade_segundos' => 300,
    'timeout' => 15,
    'modalidade_fiscal' => 0,
    'servicos' => ['04510' => 'PAC', '04014' => 'SEDEX'],
];
