<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FiscalContoraClient
{
    public function verificarAmbiente(): string
    {
        $cnpj = (string) config('services.fiscal_contora.cnpj');
        if (! config('services.fiscal_contora.token') || ! preg_match('/^\d{14}$/', $cnpj)) {
            throw new HttpException(503, 'Configure o token de homologação e o CNPJ do Fiscal Contora.');
        }
        if (config('services.fiscal_contora.ambiente') !== 'homologacao') {
            throw new HttpException(503, 'Esta integração fiscal está habilitada somente para homologação.');
        }
        $identidade = $this->requisitar('GET', '/me', $cnpj)->json();
        if (data_get($identidade, 'token.environment') !== 'homologacao') {
            throw new HttpException(503, 'Utilize uma chave Fiscal Contora de homologação.');
        }

        return $cnpj;
    }

    public function criarRascunho(array $payload, string $cnpj, string $chave): array
    {
        return $this->dados($this->requisitar('POST', '/nfe/drafts', $cnpj, $payload, $chave));
    }

    public function transmitir(string $id, string $cnpj, string $chave): void
    {
        $this->requisitar('POST', $this->caminho($id).'/dispatch', $cnpj, ['action' => 'authorize'], $chave);
    }

    public function consultar(string $id, string $cnpj): array
    {
        return $this->dados($this->requisitar('GET', $this->caminho($id).'/status', $cnpj));
    }

    public function baixarXml(string $id, string $cnpj): string
    {
        return $this->requisitar('GET', $this->caminho($id).'/artifacts/xml_authorized', $cnpj, binario: true)->body();
    }

    private function caminho(string $id): string
    {
        if (! Str::isUuid($id)) {
            throw new HttpException(502, 'O provedor fiscal retornou um identificador inválido.');
        }

        return '/nfe/drafts/'.$id;
    }

    private function requisitar(string $metodo, string $caminho, string $cnpj, array $payload = [], ?string $chave = null, bool $binario = false): Response
    {
        $http = Http::baseUrl('https://fiscal.contora.com.br/api/v1')
            ->withToken((string) config('services.fiscal_contora.token'))
            ->withHeaders(['X-Company-Document' => $cnpj])
            ->accept($binario ? 'application/xml' : 'application/json')
            ->connectTimeout(5)->timeout((int) config('services.fiscal_contora.timeout', 20))
            ->withoutRedirecting();
        if ($chave !== null) {
            $http = $http->withHeaders(['Idempotency-Key' => $chave]);
        }

        try {
            $resposta = $http->send($metodo, ltrim($caminho, '/'), $metodo === 'GET' ? [] : ['json' => $payload]);
        } catch (ConnectionException) {
            throw new HttpException(503, 'O provedor fiscal não respondeu. Repita a operação com a mesma idempotency_key.');
        }
        if (! $resposta->successful() || (! $binario && $resposta->json('ok') !== true)) {
            $status = match ($resposta->status()) {
                409 => 409,
                422 => 422,
                429 => 503,
                default => 502,
            };
            throw new HttpException($status, 'Fiscal Contora não concluiu a operação. Confira os dados e o painel do provedor; mantenha a mesma idempotency_key ao repetir.');
        }

        return $resposta;
    }

    private function dados(Response $resposta): array
    {
        $dados = $resposta->json('data');
        if (! is_array($dados)) {
            throw new HttpException(502, 'Resposta inválida do provedor fiscal.');
        }

        return $dados;
    }
}
