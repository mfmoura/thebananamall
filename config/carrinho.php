<?php

return [
    'ttl' => max(1, (int) env('CARRINHO_TTL', 604800)),
    'lock_ttl' => max(1, (int) env('CARRINHO_LOCK_TTL', 120)),
    'lock_wait' => max(1, (int) env('CARRINHO_LOCK_WAIT', 10)),
];
