<?php

namespace App\Http\Controllers\Web;

use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use League\OAuth2\Server\AuthorizationServer;

class DashboardAuthorizationController extends AuthorizationController
{
    public function __construct(AuthorizationServer $server, ClientRepository $clients)
    {
        parent::__construct($server, Auth::guard('dashboard'), $clients);
    }
}
