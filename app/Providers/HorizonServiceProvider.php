<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization()
    {
        Horizon::auth(function ($request): bool {
            $usuario = $request->user('api');

            return $usuario?->email_validado === true
                && ! $usuario->must_change_password && ! $usuario->must_reauthenticate
                && $usuario->administrador?->acessaPagina('manutencao') === true
                && $usuario->tokenCan('admin:manage');
        });
    }
}
