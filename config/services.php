<?php

return [
    'fiscal_contora' => [
        'serie' => (int) env('FISCAL_CONTORA_SERIE', 1),
        'uf_emitente' => env('FISCAL_CONTORA_UF_EMITENTE'),
        'token' => env('FISCAL_CONTORA_TOKEN'),
        'cnpj' => env('FISCAL_CONTORA_CNPJ'),
        'ambiente' => env('FISCAL_CONTORA_AMBIENTE', 'homologacao'),
        'timeout' => (int) env('FISCAL_CONTORA_TIMEOUT', 20),
    ],

    'password_pepper' => env('PASSWORD_PEPPER', env('APP_KEY')),

    'pwned_passwords' => [
        'url' => env('PWNED_PASSWORDS_URL', 'https://api.pwnedpasswords.com/range'),
        'timeout' => (int) env('PWNED_PASSWORDS_TIMEOUT', 4),
        'cache_minutes' => (int) env('PWNED_PASSWORDS_CACHE_MINUTES', 60),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', 'http://localhost'),
        'oauth_redirect_uri' => env('OAUTH_REDIRECT_URI', 'http://localhost/oauth/callback'),
    ],

    'email_verification' => [
        'ttl' => (int) env('EMAIL_VERIFICATION_TTL', 300),
    ],

    'password_recovery' => [
        'ttl' => (int) env('PASSWORD_RECOVERY_TTL', 600),
    ],

    'initial_admin' => [
        'email' => env('ADMIN_EMAIL', 'admin-teste@example.test'),
        'password' => env('ADMIN_INITIAL_PASSWORD'),
    ],

    'viacep' => [
        'base_url' => env('VIACEP_BASE_URL', 'https://viacep.com.br'),
        'timeout' => (int) env('VIACEP_TIMEOUT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
