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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Models\Pedido::observe(\App\Observers\PedidoObserver::class);
        Passport::useClientModel(OAuthClient::class);
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
