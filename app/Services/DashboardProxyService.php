<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardProxyService
{
    private const ROTAS = [
        'GET' => ['categorias', 'admin/produtos', 'admin/produtos/\d+', 'admin/usuarios', 'admin/usuarios/\d+'],
        'POST' => ['admin/produtos', 'admin/produtos/\d+/imagens', 'admin/produtos/\d+/imagens/upload', 'admin/produtos/\d+/precos', 'admin/usuarios', 'admin/usuarios/\d+/email'],
        'PATCH' => ['admin/produtos/\d+', 'admin/produtos/\d+/imagens/\d+', 'admin/usuarios/\d+'],
        'DELETE' => ['admin/produtos/\d+', 'admin/produtos/\d+/imagens/\d+', 'admin/usuarios/\d+'],
    ];

    public function __construct(private readonly BffApiService $api, private readonly DashboardOAuthService $oauth) {}

    public function encaminhar(Request $request, string $path): Response
    {
        $permitidas = self::ROTAS[$request->method()] ?? [];
        abort_unless($permitidas && preg_match('~^(?:'.implode('|', $permitidas).')$~D', $path), 404);
        $pagina = preg_match('~^admin/usuarios(?:/|$)~', $path) ? 'usuarios' : 'estoque';
        abort_unless($request->user('dashboard')?->administrador?->acessaPagina($pagina), 403);
        $token = $this->oauth->token($request);
        abort_unless($token, 401, 'Entre novamente no painel.');
        $query = $request->query();
        $response = $this->api->executar($request, '/api/'.$path.($query ? '?'.http_build_query($query) : ''),
            $request->method(), $request->isJson() ? $request->json()->all() : $request->request->all(),
            ['Authorization' => 'Bearer '.$token], $request->allFiles());
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
