<?php

namespace App\Webservices;

use Illuminate\Support\Facades\Http;

class HubCorreiosClient
{
    public function cotar(string $cep, array $pacote, string $codigo): ?array
    {
        $token = trim((string) config('frete.token'));
        abort_if($token === '', 503, 'Cotação de frete temporariamente indisponível.');
        try {
            $response = Http::acceptJson()->connectTimeout(4)->timeout(config('frete.timeout', 15))
                ->withoutRedirecting()->get('https://ws.hubdodesenvolvedor.com.br/v2/correios/', [
                    'servico' => 'calculoFrete', 'cepOrigem' => config('frete.cep_origem'), 'cepDestino' => $cep,
                    'altura' => $pacote['altura_cm'], 'largura' => $pacote['largura_cm'],
                    'comprimento' => $pacote['comprimento_cm'],
                    // Conversão explícita: neste endpoint o teste confirmou 0.5 para uma encomenda de 500 g.
                    'peso' => $pacote['peso_gramas'] / 1000, 'formato' => 1, 'tipoServico' => $codigo, 'token' => $token,
                ]);
            if (! $response->successful()) {
                return null;
            }
            $dados = $response->json();
            if (! is_array($dados) || ($dados['status'] ?? null) !== true || ($dados['return'] ?? null) !== 'OK') {
                return null;
            }

            return is_array($dados['result'] ?? null) ? $dados['result'] : null;
        } catch (\Illuminate\Http\Client\ConnectionException) {
            // Não propagar exception de HTTP: sua mensagem pode conter a URL com o token.
            return null;
        }
    }
}
