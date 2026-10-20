<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class UsuarioExclusaoService
{
    public function __construct(private readonly CarrinhoService $carrinhoService) {}

    public function excluir(Usuario $usuario, bool $somenteExpirado = false): bool
    {
        return $this->carrinhoService->comBloqueioDeEstoque(fn () => DB::transaction(function () use ($usuario, $somenteExpirado): bool {
            $conta = Usuario::query()->lockForUpdate()->find($usuario->id);
            if ($conta === null) {
                return false;
            }
            if ($somenteExpirado && ($conta->login === Usuario::LOGIN_ADMINISTRADOR_PRINCIPAL
                || $conta->administrador()->exists()
                || $conta->email_validado || $conta->last_login_at !== null
                || $conta->email_confirmation_expires_at === null || $conta->email_confirmation_expires_at->isFuture()
                || $conta->visitante()->exists())) {
                return false;
            }

            foreach ([$conta->cliente, $conta->visitante] as $dono) {
                if ($dono !== null) {
                    $this->carrinhoService->removerSobBloqueio($dono);
                }
            }
            $conta->cliente?->cartoes()->delete();
            foreach (['cliente', 'administrador', 'logistica', 'visitante', 'enderecos', 'historicoSenhas'] as $relacao) {
                $conta->{$relacao}()->delete();
            }
            $tokens = $conta->tokens()->pluck('id');
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
            $conta->tokens()->update(['revoked' => true]);

            return (bool) $conta->delete();
        }));
    }

    public function limparExpirados(): int
    {
        $total = 0;
        Usuario::query()->where('email_validado', false)->whereNull('last_login_at')
            ->where('login', '<>', Usuario::LOGIN_ADMINISTRADOR_PRINCIPAL)
            ->whereDoesntHave('administrador')
            ->where('email_confirmation_expires_at', '<=', now())->whereDoesntHave('visitante')
            ->chunkById(100, function ($usuarios) use (&$total): void {
                foreach ($usuarios as $usuario) {
                    $total += (int) $this->excluir($usuario, somenteExpirado: true);
                }
            });

        return $total;
    }
}
