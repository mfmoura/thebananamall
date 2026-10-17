<?php

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiResponse
{
    public static function wrap(mixed $data, int $statusCode): JsonResponse
    {
        if ($statusCode === 204) {
            $statusCode = 200;
        }

        $status = $statusCode >= 400 ? 'error' : 'success';

        return response()->json([
            'status' => $status,
            'statusCode' => $statusCode,
            'data' => $data,
        ], $statusCode);
    }

    public static function fromException(Throwable $exception): JsonResponse
    {
        if ($exception instanceof HttpResponseException) {
            $response = $exception->getResponse();
            $content = $response->getContent();
            $data = null;

            if (is_string($content) && $content !== '') {
                $data = json_decode($content, true);
            }

            if (! is_array($data)) {
                $data = ['message' => 'Não foi possível processar a solicitação.'];
            }

            return self::wrap($data, $response->getStatusCode());
        }

        $statusCode = match (true) {
            $exception instanceof ValidationException => $exception->status,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof ModelNotFoundException => 404,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        $message = match ($statusCode) {
            401 => 'Autenticação necessária.',
            403 => 'Acesso negado.',
            404 => 'Recurso não encontrado.',
            405 => 'Esta operação não está disponível.',
            408 => 'A solicitação demorou demais. Tente novamente.',
            413 => 'Os dados enviados ultrapassam o tamanho permitido.',
            419 => 'Sua sessão expirou. Atualize a página e tente novamente.',
            422 => 'Há erros nos dados enviados.',
            429 => 'Muitas tentativas. Aguarde um momento e tente novamente.',
            502, 503 => 'O serviço está temporariamente indisponível. Tente novamente em instantes.',
            504 => 'O serviço demorou para responder. Tente novamente.',
            default => $statusCode >= 500
                ? ($statusCode === 500 ? 'Ocorreu um erro interno.' : $exception->getMessage())
                : ($exception->getMessage() ?: 'Não foi possível processar a solicitação.'),
        };
        $data = ['message' => $message];

        if ($exception instanceof ValidationException) {
            $data['errors'] = $exception->errors();
        }

        return self::wrap($data, $statusCode);
    }
}
