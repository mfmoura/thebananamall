<?php

namespace App\Services;

use App\Models\Usuario;
use App\Notifications\ConfirmarEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function __construct(private readonly PoliticaSenhaService $politicaSenhaService) {}

    public function enviarConfirmacaoInicial(Usuario $usuario): void
    {
        if (! $usuario->email_validado) {
            $this->enviarToken($usuario, $usuario->email, 'confirmar');
        }
    }

    public function reenviarConfirmacao(string $email): void
    {
        $usuario = Usuario::query()->where('email', $email)->where('email_validado', false)
            ->where('email_confirmation_expires_at', '>', now())->latest('id')->first();

        if ($usuario !== null && ! $usuario->email_validado) {
            $this->enviarToken($usuario, $usuario->email, 'confirmar');
        }
    }

    public function solicitarTroca(Usuario $usuario, string $novoEmail): void
    {
        if (mb_strtolower($usuario->email) === mb_strtolower($novoEmail)) {
            throw ValidationException::withMessages([
                'email' => 'O novo e-mail deve ser diferente do e-mail atual.',
            ]);
        }

        $emUso = Usuario::query()
            ->where('email', $novoEmail)
            ->where('email_validado', true)
            ->where('id', '<>', $usuario->getKey())
            ->exists();

        if ($emUso) {
            throw ValidationException::withMessages([
                'email' => 'Este e-mail já está em uso.',
            ]);
        }

        $this->enviarToken($usuario, $novoEmail, 'trocar');
    }

    public function confirmar(string $token, ?string $novaSenha = null): Usuario
    {
        $tokenHash = hash('sha256', $token);
        $chaveToken = $this->chaveToken($tokenHash);
        $json = Redis::get($chaveToken);

        if (! is_string($json)) {
            throw ValidationException::withMessages([
                'token' => 'O link de confirmação é inválido ou expirou.',
            ]);
        }

        $dados = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $usuario = Usuario::query()->find($dados['usuario_id'] ?? null);

        if ($usuario === null) {
            Redis::del($chaveToken);
            throw ValidationException::withMessages([
                'token' => 'O link de confirmação é inválido ou expirou.',
            ]);
        }

        if (($dados['acao'] ?? null) === 'trocar') {
            $pendenteJson = Redis::get($this->chaveEmailPendente($usuario->id));
            $pendente = is_string($pendenteJson)
                ? json_decode($pendenteJson, true, 512, JSON_THROW_ON_ERROR)
                : null;

            if (
                ! is_array($pendente)
                || ($pendente['token_hash'] ?? null) !== $tokenHash
                || ($pendente['email'] ?? null) !== ($dados['email'] ?? null)
            ) {
                throw ValidationException::withMessages([
                    'token' => 'O link de confirmação é inválido ou expirou.',
                ]);
            }

            if ($novaSenha === null) {
                throw ValidationException::withMessages([
                    'password' => 'Escolha uma nova senha para concluir a troca do e-mail.',
                ]);
            }

            $this->politicaSenhaService->validarNova($usuario, $novaSenha, [$dados['email'] ?? null]);

            $emUso = Usuario::query()
                ->where('email', $dados['email'])
                ->where('email_validado', true)
                ->where('id', '<>', $usuario->getKey())
                ->exists();

            if ($emUso) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já está em uso.',
                ]);
            }
        } elseif (($dados['acao'] ?? null) !== 'confirmar' || $usuario->email !== ($dados['email'] ?? null)) {
            throw ValidationException::withMessages([
                'token' => 'O link de confirmação é inválido ou expirou.',
            ]);
        }

        DB::transaction(function () use (&$usuario, $dados, $novaSenha, $chaveToken): void {
            // Serializa confirmações do mesmo e-mail, inclusive de contas distintas.
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', [$dados['email']]);
            }
            $usuario = Usuario::query()->lockForUpdate()->find($usuario->id);
            if ($usuario === null || ! Redis::exists($chaveToken)) {
                throw ValidationException::withMessages(['token' => 'O link de confirmação é inválido ou expirou.']);
            }
            if (Usuario::query()->where('email', $dados['email'])->where('email_validado', true)
                ->where('id', '<>', $usuario->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Este e-mail já está em uso por uma conta confirmada.']);
            }
            if (($dados['acao'] ?? null) === 'trocar') {
                $usuario->email = $dados['email'];
                $this->politicaSenhaService->alterar($usuario, $novaSenha, [$dados['email'] ?? null]);
                $usuario->must_change_password = false;
                $usuario->must_reauthenticate = true;
            }

            $usuario->email_validado = true;
            $usuario->email_verified_at = now();
            $usuario->email_confirmation_expires_at = null;
            $usuario->save();
            $usuario->tokens()->delete();
        });

        Redis::del($chaveToken);

        if (($dados['acao'] ?? null) === 'trocar') {
            Redis::del($this->chaveEmailPendente($usuario->id));
        }

        return $usuario->refresh();
    }

    private function enviarToken(Usuario $usuario, string $email, string $acao): void
    {
        $ttl = max(1, (int) config('services.email_verification.ttl', 300));
        $token = Str::random(64);
        $tokenHash = hash('sha256', $token);
        $expiresAt = now()->addSeconds($ttl)->toIso8601String();
        if ($acao === 'confirmar') {
            $permitido = DB::transaction(function () use ($usuario, $expiresAt): bool {
                $conta = Usuario::query()->lockForUpdate()->find($usuario->id);
                if ($conta === null || $conta->email_validado
                    || ($conta->email_confirmation_expires_at !== null && $conta->email_confirmation_expires_at->isPast())) {
                    return false;
                }
                $conta->forceFill(['email_confirmation_expires_at' => $expiresAt])->save();

                return true;
            });
            if (! $permitido) {
                return;
            }
        }
        $dados = [
            'usuario_id' => $usuario->id,
            'email' => $email,
            'acao' => $acao,
            'expira_em' => $expiresAt,
        ];

        Redis::setex($this->chaveToken($tokenHash), $ttl, json_encode($dados, JSON_THROW_ON_ERROR));

        if ($acao === 'trocar') {
            Redis::setex(
                $this->chaveEmailPendente($usuario->id),
                $ttl,
                json_encode(['email' => $email, 'token_hash' => $tokenHash], JSON_THROW_ON_ERROR),
            );
        }

        $query = http_build_query([
            'token' => $token,
            'requires_password' => $acao === 'trocar' ? '1' : '0',
        ]);
        $url = rtrim((string) config('services.frontend.url'), '/').'/email/verify?'.$query;

        Notification::route('mail', $email)->notify(new ConfirmarEmailNotification(
            $url,
            $acao === 'trocar',
            (int) ceil($ttl / 60),
            $usuario->id,
        ));
    }

    private function chaveToken(string $tokenHash): string
    {
        return "email:verificacao:{$tokenHash}";
    }

    private function chaveEmailPendente(int $usuarioId): string
    {
        return "email:pendente:usuario:{$usuarioId}";
    }
}
