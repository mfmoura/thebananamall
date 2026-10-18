<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormatApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            || $response instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse) {
            return $response;
        }
        $statusCode = $response->getStatusCode();

        // HTTP 204 forbids a response body; use 200 so every API response has the envelope.
        if ($statusCode === Response::HTTP_NO_CONTENT) {
            $statusCode = Response::HTTP_OK;
        }

        $content = $response->getContent();
        $data = null;

        if (is_string($content) && $content !== '') {
            $decoded = json_decode($content, true);

            if (is_array($decoded)
                && array_key_exists('status', $decoded)
                && array_key_exists('statusCode', $decoded)
                && array_key_exists('data', $decoded)) {
                return $response;
            }

            $data = json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
        }

        $formatted = ApiResponse::wrap($data, $statusCode);

        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Cache-Control', 'Vary'] as $header) {
            if ($response->headers->has($header)) {
                $formatted->headers->set($header, $response->headers->get($header));
            }
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $formatted->headers->setCookie($cookie);
        }

        return $formatted;
    }
}
