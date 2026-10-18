<?php

namespace App\Services;

class DashboardOAuthService extends BffOAuthService
{
    protected string $prefixo = 'dashboard.oauth';
    protected string $guard = 'dashboard';
    protected string $authorizationPath = '/dashboard/oauth/authorize';

    protected function redirectUri(): string
    {
        return config('bff.dashboard_redirect_uri');
    }

    protected function scopes(): string
    {
        return 'catalog:read admin:manage';
    }
}
