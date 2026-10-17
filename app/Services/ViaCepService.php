<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ViaCepService
{
    /** @param array<string, mixed> $endereco */
    public function confirmarEndereco(array $endereco, string $prefixoCampo = ''): void
    {
        $dados = $this->consultar((string) $endereco['cep'], $prefixoCampo);

        if (strtoupper((string) $dados['uf']) !== strtoupper((string) $endereco['estado'])) {
            throw ValidationException::withMessages([$this->campo($prefixoCampo, 'estado') => 'O estado não corresponde ao CEP informado.']);
        }
        if ($this->normalizar((string) $dados['localidade']) !== $this->normalizar((string) $endereco['cidade'])) {
            throw ValidationException::withMessages([$this->campo($prefixoCampo, 'cidade') => 'A cidade não corresponde ao CEP informado.']);
        }
    }

    public function consultar(string $cep, string $prefixoCampo = ''): array
    {
        $cep = preg_replace('/\\D/', '', $cep);
        if (! preg_match('/^\\d{8}$/', $cep)) {
            throw ValidationException::withMessages([$this->campo($prefixoCampo, 'cep') => 'Informe um CEP com oito dígitos.']);
        }
        $url = rtrim((string) config('services.viacep.base_url'), '/');

        try {
            $resposta = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout((int) config('services.viacep.timeout', 8))
                ->get("{$url}/ws/{$cep}/json/");
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Não foi possível consultar o ViaCEP. Tente novamente.', $exception);
        }

        if ($resposta->status() === 400) {
            throw ValidationException::withMessages([$this->campo($prefixoCampo, 'cep') => 'O CEP informado é inválido.']);
        }

        if (! $resposta->successful()) {
            throw new ServiceUnavailableHttpException(null, 'Não foi possível confirmar o CEP no ViaCEP. Tente novamente.');
        }

        $dados = $resposta->json();

        if (is_array($dados) && ($dados['erro'] ?? false) === true) {
            throw ValidationException::withMessages([$this->campo($prefixoCampo, 'cep') => 'O CEP não foi encontrado no ViaCEP.']);
        }

        if (! is_array($dados) || ! isset($dados['cep'], $dados['uf'], $dados['localidade'])) {
            throw new ServiceUnavailableHttpException(null, 'O ViaCEP retornou uma resposta de CEP inválida.');
        }

        return $dados;
    }

    private function campo(string $prefixo, string $nome): string
    {
        return $prefixo === '' ? $nome : "{$prefixo}.{$nome}";
    }

    private function normalizar(string $valor): string
    {
        return Str::lower(Str::ascii(trim($valor)));
    }
}
