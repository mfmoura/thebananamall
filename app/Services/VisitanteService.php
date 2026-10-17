<?php

namespace App\Services;

use App\Models\Usuario;
use App\Models\Visitante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisitanteService
{
    /** @return array{visitante_id: int, token: string, expira_em: string} */
    public function criar(): array
    {
        return DB::transaction(function (): array {
            $idUnico = (string) Str::uuid();
            $token = Str::random(64);
            $expiraEm = now()->addSeconds((int) config('carrinho.visitante_ttl'));

            $usuario = Usuario::query()->create([
                'login' => "visitante-{$idUnico}",
                'email' => "visitante-{$idUnico}@guest.invalid",
                'password' => Str::random(128),
            ]);

            $visitante = $usuario->visitante()->create([
                'token_hash' => hash('sha256', $token),
                'expira_em' => $expiraEm,
            ]);

            return [
                'visitante_id' => $visitante->id,
                'token' => $token,
                'expira_em' => $expiraEm->toIso8601String(),
            ];
        });
    }

    public function buscarPorToken(string $token): ?Visitante
    {
        return Visitante::query()
            ->where('token_hash', hash('sha256', $token))
            ->where('expira_em', '>', now())
            ->first();
    }

    public function renovar(Visitante $visitante): void
    {
        $visitante->update(['expira_em' => now()->addSeconds((int) config('carrinho.visitante_ttl'))]);
    }

    public function invalidar(Visitante $visitante): void
    {
        $visitante->update([
            'token_hash' => hash('sha256', Str::random(64)),
            'expira_em' => now(),
        ]);
    }
}
