<?php

return [
    'nfe_io' => [
        'token' => env('NFE_IO_TOKEN'),
        'company_id' => env('NFE_IO_COMPANY_ID'),
        'state_tax_id' => env('NFE_IO_STATE_TAX_ID'),
        'cnpj' => env('NFE_IO_CNPJ'),
        'uf_emitente' => env('NFE_IO_UF_EMITENTE'),
        'serie' => (int) env('NFE_IO_SERIE', 1),
        'ambiente' => env('NFE_IO_AMBIENTE', 'homologacao'),
        'timeout' => (int) env('NFE_IO_TIMEOUT', 20),
        'download_hosts' => array_filter(explode(',', env('NFE_IO_DOWNLOAD_HOSTS', ''))),
    ],

    'password_pepper' => env('PASSWORD_PEPPER', env('APP_KEY')),

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
