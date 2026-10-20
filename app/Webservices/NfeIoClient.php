<?php

namespace App\Webservices;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NfeIoClient
{
    public function verificarAmbiente(): string
    {
        $cnpj = (string) config('services.nfe_io.cnpj');
        if (! config('services.nfe_io.token') || ! preg_match('/^\d{14}$/', $cnpj)
            || config('services.nfe_io.ambiente') !== 'homologacao') {
            throw new HttpException(503, 'Configure a NFE.io exclusivamente em homologação, com token e CNPJ.');
        }
        $empresa = $this->dados($this->requisitar('GET', $this->empresa()), 'company');
        $ie = $this->dados($this->requisitar('GET', $this->inscricao()), 'stateTax');
        if (str_pad((string) ($empresa['federalTaxNumber'] ?? ''), 14, '0', STR_PAD_LEFT) !== $cnpj
            || strtolower((string) ($ie['environmentType'] ?? '')) !== 'test'
            || strtolower((string) ($ie['type'] ?? '')) !== 'nfe'
            || strtoupper((string) ($ie['code'] ?? '')) !== config('services.nfe_io.uf_emitente')
            || (int) ($ie['serie'] ?? 0) !== (int) config('services.nfe_io.serie')) {
            throw new HttpException(503, 'Confira empresa, UF, série e inscrição estadual NF-e Test na NFE.io.');
        }

        return $cnpj;
    }

    public function emitir(array $payload): array
    {
        // A API não oferece idempotência no POST. Não usar retry HTTP.
        return $this->dados($this->requisitar('POST', $this->inscricao().'/productinvoices', $payload));
    }

    public function consultar(string $id): array
    {
        return $this->dados($this->requisitar('GET', $this->empresa().'/productinvoices/'.$this->identificador($id)));
    }

    public function baixarXml(string $id): string
    {
        $resposta = $this->requisitar('GET', $this->empresa().'/productinvoices/'.$this->identificador($id).'/xml');
        $uri = $resposta->json('uri');
        $partes = is_string($uri) ? parse_url($uri) : false;
        if (! is_array($partes) || ($partes['scheme'] ?? '') !== 'https'
            || isset($partes['user']) || isset($partes['pass']) || isset($partes['port'])
            || ! in_array($partes['host'] ?? '', config('services.nfe_io.download_hosts', []), true)) {
            throw new HttpException(502, 'Host do XML NFE.io não autorizado. Configure NFE_IO_DOWNLOAD_HOSTS com o host exato do armazenamento do provedor.');
        }
        try {
            // Requisição separada, sem Authorization nem redirects para o armazenamento assinado.
            $arquivo = Http::connectTimeout(5)->timeout((int) config('services.nfe_io.timeout', 20))
                ->withoutRedirecting()->get($uri);
        } catch (ConnectionException) {
            throw new HttpException(503, 'Não foi possível baixar o XML fiscal.');
        }
        if (! $arquivo->successful()) {
            throw new HttpException(502, 'XML fiscal indisponível na NFE.io.');
        }

        return $arquivo->body();
    }

    public function identificador(string $id): string
    {
        if (! preg_match('/^[a-zA-Z0-9-]{8,64}$/D', $id)) {
            throw new HttpException(503, 'Identificador NFE.io ausente ou inválido.');
        }

        return $id;
    }

    private function empresa(): string
    {
        return 'companies/'.$this->identificador((string) config('services.nfe_io.company_id'));
    }

    private function inscricao(): string
    {
        return $this->empresa().'/statetaxes/'.$this->identificador((string) config('services.nfe_io.state_tax_id'));
    }

    private function requisitar(string $metodo, string $caminho, array $payload = []): Response
    {
        try {
            $resposta = Http::baseUrl('https://api.nfse.io/v2/')
                ->withHeaders(['Authorization' => (string) config('services.nfe_io.token')])
                ->acceptJson()->connectTimeout(5)->timeout((int) config('services.nfe_io.timeout', 20))
                ->withoutRedirecting()->send($metodo, $caminho, $metodo === 'GET' ? [] : ['json' => $payload]);
        } catch (ConnectionException) {
            throw new HttpException(503, 'NFE.io não respondeu. Consulte a emissão original antes de tentar novamente.');
        }
        if (! $resposta->successful()) {
            throw new HttpException(match ($resposta->status()) {
                400, 422 => 422, 409 => 409, 429, 503 => 503, default => 502,
            }, 'NFE.io não concluiu a operação. Confira os dados e a emissão original no painel.');
        }

        return $resposta;
    }

    private function dados(Response $resposta, ?string $envelope = null): array
    {
        $dados = $envelope === null ? $resposta->json() : $resposta->json($envelope);
        if (! is_array($dados) || array_is_list($dados)) {
            throw new HttpException(502, 'Resposta NFE.io inválida.');
        }

        return $dados;
    }
}
