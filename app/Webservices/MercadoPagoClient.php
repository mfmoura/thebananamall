<?php

namespace App\Webservices;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MercadoPagoClient
{
    public function verificarConfiguracao(): void
    {
        $this->credencial();
    }

    public function criarOrder(array $dados, string $chave): array
    {
        return $this->chamar('post', '/v1/orders', $dados, $chave);
    }

    public function consultarOrder(string $id): array
    {
        return $this->chamar('get', '/v1/orders/'.$id);
    }

    private function credencial(): string
    {
        $token = trim((string) config('mercadopago.access_token'));
        abort_unless($token !== '', 503, 'Pagamento temporariamente indisponível.');

        return $token;
    }

    private function chamar(string $metodo, string $path, array $dados = [], ?string $key = null): array
    {
        $http = Http::withToken($this->credencial())->acceptJson()->connectTimeout(5)->timeout(config('mercadopago.timeout'));
        if ($key) {
            $http = $http->withHeaders(['X-Idempotency-Key' => $key]);
        }
        try {
            $response = $http->{$metodo}('https://api.mercadopago.com'.$path, $dados);
        } catch (ConnectionException) {
            abort(503, 'Provedor indisponível. Tente novamente com os mesmos dados.');
        }
        $json = $response->json();
        // Uma recusa pode criar a Order e retorná-la em data com HTTP 402.
        // Aproveita somente o ID; o service confirma o resultado por GET.
        if ($metodo === 'post' && $path === '/v1/orders' && $response->status() === 402
            && data_get($json, 'data.status') === 'failed'
            && is_string($id = data_get($json, 'data.id'))
            && preg_match('/^[a-zA-Z0-9]+$/D', $id)) {
            return ['id' => $id];
        }
        abort_unless($response->successful(), 502, 'Não foi possível processar o pagamento.');
        abort_unless(is_array($json), 502, 'Resposta inválida do provedor.');

        return $json;
    }
}
