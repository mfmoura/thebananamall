<?php

return [
    'ttl' => max(1, (int) env('CARRINHO_TTL', 604800)),
];
