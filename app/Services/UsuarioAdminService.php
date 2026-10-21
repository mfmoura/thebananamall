<?php

namespace App\Services;

use App\Enums\TipoUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

class UsuarioAdminService
{
    public function __construct(
        private readonly UsuarioService $usuarios,
        private readonly CarrinhoService $carrinhos,
    ) {}

    private function consulta(): Builder
    {
        return Usuario::query()->whereDoesntHave('visitante')
            ->select(['id', 'login', 'email', 'email_validado', 'created_at', 'last_login_at'])
            ->with(['cliente:id,usuario_id', 'administrador:id,usuario_id,paginas', 'logistica:id,usuario_id,nome,webhook']);
    }

    public function listar(array $dados)
    {
        $query = $this->consulta()->orderBy('login')->orderBy('id');
        if (! empty($dados['tipo'])) $query->whereHas($dados['tipo']);
        $busca = trim($dados['busca'] ?? '');
        if ($busca !== '') {
            $padrao = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $busca).'%';
            $query->where(fn ($q) => $q->whereRaw("lower(login) like lower(?) escape '!'", [$padrao])
                ->orWhereRaw("lower(email) like lower(?) escape '!'", [$padrao]));
        }
        return $query->paginate((int) ($dados['per_page'] ?? 10), ['*'], 'page', (int) ($dados['page'] ?? 1));
    }

    public function consultar(Usuario $usuario): Usuario
    {
        return $this->consulta()->findOrFail($usuario->id);
    }

    public function criar(array $dados): Usuario
    {
        $tipo = TipoUsuario::from(Arr::pull($dados, 'tipo'));
        $usuario = match ($tipo) {
            TipoUsuario::Cliente => $this->usuarios->criarCliente($dados),
            TipoUsuario::Administrador => $this->usuarios->criarAdministrador($dados),
            TipoUsuario::Logistica => $this->usuarios->criarLogistica($dados),
        };
        return $this->consultar($usuario);
    }

    public function atualizar(Usuario $usuario, array $dados): Usuario
    {
        $atualizar = fn () => DB::transaction(function () use ($usuario, $dados): void {
            $usuario = Usuario::query()->lockForUpdate()->findOrFail($usuario->id);
            $acessoLojaAlterado = false;
            if (array_key_exists('acesso_loja', $dados)) {
                $cliente = $usuario->cliente()->withTrashed()->first();
                $acessoAtual = $cliente !== null && ! $cliente->trashed();
                $acessoLojaAlterado = $acessoAtual !== (bool) $dados['acesso_loja'];
                if ($acessoLojaAlterado) {
                    if ($dados['acesso_loja']) {
                        $cliente ? $cliente->restore() : $usuario->cliente()->create([]);
                    } else {
                        $this->carrinhos->removerSobBloqueio($cliente);
                        $cliente->delete();
                    }
                }
            }
            $paginasAtuais = $usuario->administrador?->paginas ?? [];
            $paginasNovas = $dados['paginas'] ?? $paginasAtuais;
            sort($paginasAtuais);
            sort($paginasNovas);
            $permissoesAlteradas = $paginasAtuais !== $paginasNovas;
            $this->usuarios->atualizar($usuario, Arr::only($dados, ['login', 'password']));
            if (isset($dados['paginas'])) $usuario->administrador()->firstOrFail()->update(['paginas' => array_values(array_unique($dados['paginas']))]);
            if ($usuario->logistica()->exists()) $usuario->logistica()->update(Arr::only($dados, ['nome', 'webhook']));
            if (isset($dados['password']) || $permissoesAlteradas || $acessoLojaAlterado) {
                $tokens = Passport::token()->newQuery()->where('user_id', $usuario->id);
                Passport::refreshToken()->newQuery()->whereIn('access_token_id', (clone $tokens)->select('id'))->update(['revoked' => true]);
                $tokens->update(['revoked' => true]);
                $usuario->forceFill(['must_reauthenticate' => true])->save();
            }
        });
        if (array_key_exists('acesso_loja', $dados)) {
            $this->carrinhos->comBloqueioDeEstoque($atualizar);
        } else {
            $atualizar();
        }
        return $this->consultar($usuario);
    }
}
