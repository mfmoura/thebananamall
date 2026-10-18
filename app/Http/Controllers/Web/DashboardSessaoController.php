<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\OAuthLoginRequest;
use App\Services\DashboardSessaoService;
use Illuminate\Http\Request;
use App\Services\DashboardOAuthService;
use App\Http\Requests\Web\BffCallbackRequest;

class DashboardSessaoController extends Controller
{
    public function store(OAuthLoginRequest $request, DashboardSessaoService $sessao)
    {
        $sessao->entrar($request, $request->validated());

        return response()->json(['message' => 'Acesso autorizado.'])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, DashboardSessaoService $sessao, DashboardOAuthService $oauth)
    {
        $usuario = $sessao->usuario();
        abort_unless($usuario, 401);

        return response()->json(['id' => $usuario->id, 'login' => $usuario->login, 'paginas' => $usuario->administrador->paginas, 'api_autorizada' => $oauth->autenticado($request)])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, DashboardSessaoService $sessao)
    {
        $sessao->sair($request);

        return response()->json(['message' => 'Sessão administrativa encerrada.'])->header('Cache-Control', 'no-store');
    }

    public function authorize(Request $request, DashboardOAuthService $oauth)
    {
        return response()->json(['authorization_url' => $oauth->autorizar($request)])->header('Cache-Control', 'no-store');
    }

    public function callback(BffCallbackRequest $request, DashboardOAuthService $oauth)
    {
        $oauth->confirmar($request, $request->validated());

        return redirect('/dashboard/')->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
