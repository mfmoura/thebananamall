<?php

namespace App\Services;

use App\Models\Usuario;
use App\Notifications\RecuperarSenhaNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecuperacaoSenhaService
{
    public function __construct(private readonly PoliticaSenhaService $politicaSenhaService) {}

    public function solicitar(string $email): void
    {
        $usuario = Usuario::query()->where('email', $email)->first();

        if ($usuario === null) {
            return;
        }

        $ttl = max(1, (int) config('services.password_recovery.ttl', 600));
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);
        $tokenKey = $this->chaveToken($tokenHash);
        $usuarioKey = $this->chaveUsuario($usuario->id);

        $tokenAnterior = Redis::get($usuarioKey);
        if (is_string($tokenAnterior)) {
            Redis::del($this->chaveToken($tokenAnterior));
        }

        Redis::setex($tokenKey, $ttl, json_encode([
            'usuario_id' => $usuario->id,
            'email' => $usuario->email,
        ], JSON_THROW_ON_ERROR));
        Redis::setex($usuarioKey, $ttl, $tokenHash);

        $url = rtrim((string) config('services.frontend.url'), '/')
            .'/password/reset?'.http_build_query(['token' => $token]);

        Notification::route('mail', $usuario->email)->notify(new RecuperarSenhaNotification(
            $url,
            (int) ceil($ttl / 60),
        ));
    }

    public function redefinir(string $token, string $novaSenha): Usuario
    {
        $tokenHash = hash('sha256', $token);
        $tokenKey = $this->chaveToken($tokenHash);
        return Cache::lock("lock:{$tokenKey}", 30)->block(5, function () use (
            $tokenHash,
            $tokenKey,
            $novaSenha,
        ): Usuario {
            $dadosJson = Redis::get($tokenKey);
            $dados = is_string($dadosJson) ? json_decode($dadosJson, true) : null;

            if (! is_array($dados) || ! hash_equals(
                (string) Redis::get($this->chaveUsuario((int) ($dados['usuario_id'] ?? 0))),
                $tokenHash,
            )) {
                throw $this->tokenInvalido();
            }

            $usuario = Usuario::query()->find($dados['usuario_id'] ?? null);
            if ($usuario === null || $usuario->email !== ($dados['email'] ?? null)) {
                Redis::del($tokenKey);
                throw $this->tokenInvalido();
            }

            DB::transaction(function () use ($usuario, $novaSenha, $tokenKey): void {
                $this->politicaSenhaService->alterar($usuario, $novaSenha);
                $usuario->must_change_password = false;
                $usuario->must_reauthenticate = true;
                $usuario->save();
                $usuario->tokens()->delete();

            });

            Redis::del($tokenKey);
            Redis::del($this->chaveUsuario($usuario->id));

            return $usuario->refresh();
        });
    }

    private function tokenInvalido(): ValidationException
    {
        return ValidationException::withMessages([
            'token' => 'O link de recuperação é inválido ou expirou.',
        ]);
    }

    private function chaveToken(string $tokenHash): string
    {
        return "senha:recuperacao:token:{$tokenHash}";
    }

    private function chaveUsuario(int $usuarioId): string
    {
        return "senha:recuperacao:usuario:{$usuarioId}";
    }
}
