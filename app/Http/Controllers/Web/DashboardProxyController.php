<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DashboardProxyService;
use Illuminate\Http\Request;

class DashboardProxyController extends Controller
{
    public function __invoke(Request $request, string $path, DashboardProxyService $proxy)
    {
        return $proxy->encaminhar($request, $path);
    }
}
