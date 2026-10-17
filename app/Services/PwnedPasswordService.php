<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PwnedPasswordService
{
    public function estaComprometida(string $senha): bool
    {
        $hash = strtoupper(sha1($senha));
        $prefixo = substr($hash, 0, 5);
        $sufixo = substr($hash, 5);
        $cacheKey = "pwned-passwords:range:v1:{$prefixo}";

        try {
            $resposta = Cache::remember(
                $cacheKey,
                now()->addMinutes(max(1, (int) config('services.pwned_passwords.cache_minutes', 60))),
                fn (): string => $this->consultarPrefixo($prefixo),
            );
        } catch (Throwable $exception) {
            Log::warning('Falha ao consultar a base Pwned Passwords.', [
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'password' => 'Nao foi possivel verificar a senha agora. Tente novamente mais tarde.',
            ]);
        }

        foreach (preg_split('/\r?\n/', $resposta, -1, PREG_SPLIT_NO_EMPTY) as $linha) {
            [$sufixoEncontrado] = array_pad(explode(':', trim($linha), 2), 1, '');

            if ($sufixoEncontrado !== '' && hash_equals($sufixo, strtoupper($sufixoEncontrado))) {
                return true;
            }
        }

        return false;
    }

    private function consultarPrefixo(string $prefixo): string
    {
        $response = Http::accept('text/plain')
            ->withHeaders(['Add-Padding' => 'true'])
            ->timeout((int) config('services.pwned_passwords.timeout', 4))
            ->get(rtrim((string) config('services.pwned_passwords.url'), '/')."/{$prefixo}");

        $body = $response->body();

        if (
            ! $response->successful()
            || ! preg_match('/\\A(?:[A-F0-9]{35}:\\d+\\r?\\n?)+\\z/i', $body)
        ) {
            throw new \RuntimeException('Pwned Passwords API returned an invalid response.');
        }

        return $body;
    }
}
