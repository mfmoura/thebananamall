<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class OAuthClientSeeder extends Seeder
{
    public function run(): void
    {
        Client::query()->updateOrCreate(
            ['id' => config('oauth.frontend_client_id')],
            [
                'owner_type' => null,
                'owner_id' => null,
                'name' => 'Loja Virtual LCB SPA',
                'secret' => null,
                'provider' => null,
                'redirect_uris' => [config('services.frontend.oauth_redirect_uri')],
                'grant_types' => ['authorization_code', 'refresh_token'],
                'revoked' => filled(config('bff.client_secret')),
            ],
        );
    }
}
