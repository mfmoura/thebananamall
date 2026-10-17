<?php

return [
    'client_id' => env('BFF_CLIENT_ID', '00000000-0000-4000-8000-000000000002'),
    'client_secret' => env('BFF_CLIENT_SECRET'),
    'redirect_uri' => rtrim(env('APP_URL', 'http://localhost'), '/').'/bff/callback',
    'scopes' => 'catalog:read cart:read cart:write orders:write',
];
