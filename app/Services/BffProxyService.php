<?php

namespace App\Services;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class BffProxyService
{
    private const ROTAS = [
        'GET' => ['enderecos/cep/\\d{8}', 'produtos', 'produtos/\d+', 'produtos/\d+/precos', 'produtos/\d+/estoque', 'categorias', 'formas-pagamento', 'pagamentos/configuracao', 'me', 'carrinho', 'pedidos', 'pedidos/\d+', 'pedidos/\d+/danfe', 'pedidos/\d+/pagamento'],
        'POST' => ['clientes', 'email/confirmar', 'email/reenviar-confirmacao', 'senha/recuperar', 'senha/redefinir', 'usuarios/\d+/email', 'enderecos', 'carrinho', 'carrinho/itens', 'carrinho/mesclar', 'pedidos', 'pedidos/\d+/cancelar', 'pedidos/\d+/pagamento'],
        'PUT' => ['usuarios/\d+', 'clientes/\d+', 'enderecos/\d+', 'carrinho'],
        'PATCH' => ['carrinho/itens/\d+'],
        'DELETE' => ['usuarios/\d+', 'clientes/\d+', 'enderecos/\d+', 'carrinho', 'carrinho/itens/\d+'],
    ];

    public function __construct(private readonly BffApiService $api, private readonly BffOAuthService $oauth, private readonly VisitanteService $visitantes) {}

    public function migrarVisitante(Request $request, string $token): void
    {
        abort_unless($this->visitantes->buscarPorToken($token), 401, 'Carrinho de visitante expirado.');
        if (! $request->session()->has('bff.visitante')) {
            $request->session()->put('bff.visitante', Crypt::encryptString($token));
        }
    }

    public function encaminhar(Request $request, string $path): Response
    {
        $method = $request->method();
        $permitidas = self::ROTAS[$method] ?? [];
        abort_unless($permitidas && preg_match('~^(?:'.implode('|', $permitidas).')$~D', $path), 404);
        $token = $this->oauth->token($request);
        $headers = $token ? ['Authorization' => 'Bearer '.$token] : [];
        if ($request->hasHeader('Idempotency-Key')) {
            $headers['Idempotency-Key'] = $request->header('Idempotency-Key');
        }

        if (str_starts_with($path, 'carrinho')) {
            $visitante = $request->session()->get('bff.visitante');
            $visitante = $visitante ? Crypt::decryptString($visitante) : null;
            if ($visitante && ! $this->visitantes->buscarPorToken($visitante)) {
                $request->session()->forget('bff.visitante');
                $visitante = null;
            }
            if ($path === 'carrinho/mesclar') {
                abort_unless($token, 401);
                if (! $visitante) return ApiResponse::wrap(['message' => 'Nenhum carrinho de visitante pendente.'], 200);
            } elseif (! $token && ! $visitante) {
                if ($method === 'GET') return ApiResponse::wrap(['itens' => []], 200);
                if ($method !== 'POST' || $path !== 'carrinho') return ApiResponse::wrap(['message' => 'Carrinho não encontrado.'], 404);
                $dados = $this->visitantes->criar();
                $visitante = $dados['token'];
                $request->session()->put('bff.visitante', Crypt::encryptString($visitante));
            }
            if ($visitante && (! $token || $path === 'carrinho/mesclar')) $headers['X-Visitante-Token'] = $visitante;
        }

        $query = $request->query();
        $url = '/api/'.$path.($query ? '?'.http_build_query($query) : '');
        $response = $this->api->executar($request, $url, $method, $request->json()->all(), $headers);
        if ($path === 'carrinho/mesclar' && $response->isSuccessful()) $request->session()->forget('bff.visitante');
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
