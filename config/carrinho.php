<?php

return [
    'ttl' => max(1, (int) env('CARRINHO_TTL', 604800)),
    'visitante_ttl' => max(1, (int) env('CARRINHO_VISITANTE_TTL', 86400)),
    'cliente_ttl' => max(1, (int) env('CARRINHO_CLIENTE_TTL', 2592000)),
    'reserva_ttl' => max(1, (int) env('CARRINHO_RESERVA_TTL', 900)),
    'lock_ttl' => max(1, (int) env('CARRINHO_LOCK_TTL', 120)),
    'lock_wait' => max(1, (int) env('CARRINHO_LOCK_WAIT', 10)),
];
