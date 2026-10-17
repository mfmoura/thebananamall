<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;

class OAuthClient extends Client
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return $this->getKey() === config('bff.client_id')
            || (! config('bff.client_secret') && $this->getKey() === config('oauth.frontend_client_id'));
    }
}
