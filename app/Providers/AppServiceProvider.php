<?php

namespace App\Providers;

use Laravel\Passport\Passport;
use App\Models\OAuthClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Pedido::observe(\App\Observers\PedidoObserver::class);
        Passport::useClientModel(OAuthClient::class);
        // Passport resolve este contrato mesmo quando o BFF dispensa consentimento.
        // Clientes que exigem consentimento manual continuam sem aprovação automática.
        Passport::authorizationView(fn () => abort(403, 'Este cliente não possui um fluxo de consentimento disponível.'));
        Passport::tokensCan([
            'catalog:read' => 'Consultar catálogo, preços e estoque',
            'cart:read' => 'Consultar o carrinho do usuário autenticado',
            'cart:write' => 'Manter o carrinho do usuário autenticado',
            'orders:write' => 'Criar e consultar pedidos do Cliente',
            'admin:manage' => 'Operações administrativas',
            'logistics:manage' => 'Operações de logística',
        ]);
        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
