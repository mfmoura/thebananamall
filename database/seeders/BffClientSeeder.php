<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;

class BffClientSeeder extends Seeder
{
    public function run(): void
    {
        $secret = config('bff.client_secret');
        if (! is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException('Configure BFF_CLIENT_SECRET com pelo menos 32 caracteres aleatórios.');
        }
        $client = Client::query()->findOrNew(config('bff.client_id'));
        $client->forceFill([
            'id' => config('bff.client_id'), 'name' => 'Loja BFF',
            'owner_type' => null, 'owner_id' => null, 'secret' => $secret,
            'provider' => null, 'redirect_uris' => [config('bff.redirect_uri'), config('bff.dashboard_redirect_uri')],
            'grant_types' => ['authorization_code', 'refresh_token'], 'revoked' => false,
        ])->save();

        // A SPA antiga não deve continuar obtendo tokens no navegador.
        \Illuminate\Support\Facades\DB::transaction(function (): void {
            $tokens = \Laravel\Passport\Passport::token()->newQuery()->where('client_id', config('oauth.frontend_client_id'));
            \Laravel\Passport\Passport::refreshToken()->newQuery()->whereIn('access_token_id', (clone $tokens)->select('id'))->update(['revoked' => true]);
            $tokens->update(['revoked' => true]);
            Client::query()->whereKey(config('oauth.frontend_client_id'))->update(['revoked' => true]);
        });
    }
}
