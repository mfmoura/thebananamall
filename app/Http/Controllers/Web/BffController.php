<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\BffCallbackRequest;
use App\Http\Requests\Web\BffVisitanteRequest;
use App\Services\BffOAuthService;
use App\Services\BffProxyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BffController extends Controller
{
    public function session(Request $request, BffOAuthService $oauth)
    {
        return response()->json(['autenticado' => $oauth->autenticado($request)])->header('Cache-Control', 'no-store');
    }

    public function authorize(Request $request, BffOAuthService $oauth)
    {
        return response()->json(['authorization_url' => $oauth->autorizar($request)])->header('Cache-Control', 'no-store');
    }

    public function callback(BffCallbackRequest $request, BffOAuthService $oauth)
    {
        $oauth->confirmar($request, $request->validated());
        return redirect('/oauth/callback')->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function proxy(Request $request, string $path, BffProxyService $service)
    {
        return $service->encaminhar($request, $path);
    }

    public function visitante(BffVisitanteRequest $request, BffProxyService $service)
    {
        $service->migrarVisitante($request, $request->validated('token'));
        return response()->json(['message' => 'Carrinho migrado para a sessão.']);
    }

    public function logout(Request $request, BffOAuthService $oauth)
    {
        $oauth->revogar($request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Sessão encerrada.'])->header('Cache-Control', 'no-store');
    }
}
