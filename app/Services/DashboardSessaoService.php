<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardSessaoService
{
    public function __construct(private readonly PoliticaSenhaService $senhas, private readonly DashboardOAuthService $oauth) {}

    public function entrar(Request $request, array $dados): void
    {
        $usuario = Usuario::query()
            ->where(fn ($query) => $query->where('login', $dados['login'])->orWhere('email', $dados['login']))
            ->orderByDesc('email_validado')->orderByDesc('id')->first();

        abort_unless($usuario && $this->senhas->corresponde($dados['password'], $usuario->password), 401, 'Credenciais inválidas.');
        abort_unless($usuario->administrador()->exists(), 403, 'Acesso restrito a administradores.');
        abort_unless($usuario->email_validado, 403, 'Confirme seu e-mail antes de entrar.');
        abort_if($usuario->must_change_password, 403, 'Troque sua senha inicial antes de entrar no painel.');

        $usuario->forceFill(['last_login_at' => now(), 'must_reauthenticate' => false])->save();
        $this->oauth->revogar($request);
        Auth::guard('dashboard')->login($usuario);
        $request->session()->regenerate();
    }

    public function usuario(): ?Usuario
    {
        $usuario = Auth::guard('dashboard')->user();

        return $usuario && ! $usuario->trashed() && $usuario->email_validado
            && ! $usuario->must_change_password && ! $usuario->must_reauthenticate
            && $usuario->administrador()->exists() ? $usuario : null;
    }

    public function sair(Request $request): void
    {
        $this->oauth->revogar($request);
        Auth::guard('dashboard')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();
    }
}
