<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Illuminate\Support\Facades\DB;

class BffOAuthService
{
    public function __construct(private readonly BffApiService $api) {}

    public function autorizar(Request $request): string
    {
        abort_unless(config('bff.client_secret'), 503, 'Autenticação temporariamente indisponível.');
        if ($request->session()->has('bff.tokens')) $this->revogar($request);
        $verifier = Str::random(64);
        $state = Str::random(64);
        $request->session()->put('bff.pkce', ['state' => $state, 'verifier' => $verifier, 'criado' => time(), 'user_id' => (string) $request->user()->getAuthIdentifier()]);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        return url('/oauth/authorize').'?'.http_build_query([
            'client_id' => config('bff.client_id'), 'redirect_uri' => config('bff.redirect_uri'),
            'response_type' => 'code', 'scope' => config('bff.scopes'),
            'state' => $state, 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
        ]);
    }

    public function confirmar(Request $request, array $dados): void
    {
        $pkce = $request->session()->pull('bff.pkce');
        abort_unless(is_array($pkce) && time() - $pkce['criado'] <= 600 && hash_equals($pkce['state'], $dados['state']) && $pkce['user_id'] === (string) $request->user()->getAuthIdentifier(), 422, 'Autorização inválida ou expirada.');
        $tokens = $this->trocar($request, ['grant_type' => 'authorization_code', 'code' => $dados['code'], 'code_verifier' => $pkce['verifier'], 'redirect_uri' => config('bff.redirect_uri')]);
        $this->guardar($request, $tokens);
        $request->session()->regenerate();
    }

    public function autenticado(Request $request): bool
    {
        return $request->session()->has('bff.tokens') && $request->user() !== null
            && ($this->ler($request)['user_id'] ?? null) === (string) $request->user()->getAuthIdentifier();
    }

    public function token(Request $request): ?string
    {
        if (! $this->autenticado($request)) {
            return null;
        }
        $tokens = $this->ler($request);
        if ($tokens['expires_at'] <= time() + 30) {
            try {
                $novos = $this->trocar($request, ['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']]);
                $this->guardar($request, $novos);
                $tokens = $this->ler($request);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                if ($exception->getStatusCode() !== 401) throw $exception;
                $this->revogar($request);
                abort(401, 'Sua sessão expirou. Entre novamente.');
            }
        }
        return $tokens['access_token'];
    }

    public function revogar(Request $request): void
    {
        if ($request->session()->has('bff.tokens')) {
            $tokens = $this->ler($request);
            // O jti é lido apenas de um token recebido diretamente do Passport.
            $partes = explode('.', $tokens['access_token']);
            $payload = json_decode(base64_decode(strtr($partes[1] ?? '', '-_', '+/')), true);
            if (isset($payload['jti'])) {
                DB::transaction(function () use ($payload): void {
                    Passport::refreshToken()->newQuery()->where('access_token_id', $payload['jti'])->update(['revoked' => true]);
                    Passport::token()->newQuery()->whereKey($payload['jti'])->where('client_id', config('bff.client_id'))->update(['revoked' => true]);
                });
            }
        }
        $request->session()->forget(['bff.tokens', 'bff.pkce']);
    }

    private function trocar(Request $request, array $body): array
    {
        $response = $this->api->executar($request, '/oauth/token', 'POST', $body + [
            'client_id' => config('bff.client_id'), 'client_secret' => config('bff.client_secret'),
        ]);
        abort_if($response->getStatusCode() >= 500, 503, 'Autorização temporariamente indisponível.');
        abort_unless($response->isSuccessful(), 401, 'Não foi possível concluir a autorização.');
        $tokens = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        abort_unless(isset($tokens['access_token'], $tokens['refresh_token'], $tokens['expires_in']), 502, 'Falha na autorização.');
        return $tokens;
    }

    private function guardar(Request $request, array $tokens): void
    {
        $partes = explode('.', $tokens['access_token']);
        $payload = json_decode(base64_decode(strtr($partes[1] ?? '', '-_', '+/')), true);
        // Confere a identidade também no registro emitido pelo Passport.
        abort_unless(isset($payload['jti']) && Passport::token()->newQuery()
            ->whereKey($payload['jti'])->where('client_id', config('bff.client_id'))
            ->where('user_id', $request->user()->getAuthIdentifier())->where('revoked', false)->exists(), 401, 'Autorização incompatível com a sessão.');
        $request->session()->put('bff.tokens', Crypt::encryptString(json_encode([
            'access_token' => $tokens['access_token'], 'refresh_token' => $tokens['refresh_token'],
            'expires_at' => time() + (int) $tokens['expires_in'],
            'user_id' => (string) $request->user()->getAuthIdentifier(),
        ], JSON_THROW_ON_ERROR)));
    }

    private function ler(Request $request): array
    {
        return json_decode(Crypt::decryptString($request->session()->get('bff.tokens')), true, 512, JSON_THROW_ON_ERROR);
    }
}
