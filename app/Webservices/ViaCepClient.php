<?php

namespace App\Webservices;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ViaCepClient
{
    public function consultar(string $cep): Response
    {
        $url = rtrim((string) config('services.viacep.base_url'), '/');

        try {
            return Http::acceptJson()
                ->connectTimeout(3)
                ->timeout((int) config('services.viacep.timeout', 8))
                ->get("{$url}/ws/{$cep}/json/");
        } catch (ConnectionException $exception) {
            throw new ServiceUnavailableHttpException(null, 'Não foi possível consultar o ViaCEP. Tente novamente.', $exception);
        }
    }
}
