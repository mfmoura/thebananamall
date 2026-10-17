<?php

namespace App\Services;

use App\Enums\TipoUsuario;
use App\Models\Administrador;
use App\Models\Cliente;
use App\Models\Logistica;
use App\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UsuarioService
{
    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
        private readonly ViaCepService $viaCepService,
        private readonly UsuarioExclusaoService $usuarioExclusaoService,
        private readonly PoliticaSenhaService $politicaSenhaService,
    ) {}

    public function consultar(Usuario $usuario): Usuario
    {
        return $usuario->load(['cliente', 'administrador', 'logistica', 'enderecos']);
    }

    public function consultarCliente(Cliente $cliente): Cliente
    {
        return $cliente->load('usuario.enderecos');
    }

    public function consultarAdministrador(Administrador $administrador): Administrador
    {
        return $administrador->load('usuario');
    }

    public function consultarLogistica(Logistica $logistica): Logistica
    {
        return $logistica->load('usuario');
    }

    public function criarCliente(array $dados): Usuario
    {
        $dados['tipo'] = TipoUsuario::Cliente->value;

        return $this->criar($dados);
    }

    public function criarAdministrador(array $dados): Usuario
    {
        $dados['tipo'] = TipoUsuario::Administrador->value;
        $dados['perfil'] = ['paginas' => $dados['paginas']];
        unset($dados['paginas']);

        return $this->criar($dados);
    }

    public function criarLogistica(array $dados): Usuario
    {
        $dados['tipo'] = TipoUsuario::Logistica->value;
        $dados['perfil'] = Arr::only($dados, ['nome', 'webhook']);
        unset($dados['nome'], $dados['webhook']);

        return $this->criar($dados);
    }

    public function criar(array $dados): Usuario
    {
        if (isset($dados['endereco'])) {
            $this->viaCepService->confirmarEndereco($dados['endereco'], 'endereco');
        }

        $usuario = DB::transaction(function () use ($dados): Usuario {
            $tipo = TipoUsuario::from($dados['tipo'] ?? TipoUsuario::Cliente->value);
            $perfil = $dados['perfil'] ?? [];
            $endereco = $dados['endereco'] ?? null;

            unset($dados['tipo'], $dados['perfil'], $dados['endereco']);

            $dados['password'] = $this->politicaSenhaService->hash($dados['password'], [
                $dados['login'] ?? null,
                $dados['email'] ?? null,
            ]);
            $usuario = Usuario::create($dados);

            match ($tipo) {
                TipoUsuario::Cliente => Cliente::create(['usuario_id' => $usuario->id]),
                TipoUsuario::Administrador => Administrador::create([
                    'usuario_id' => $usuario->id,
                    'paginas' => array_values(array_unique($perfil['paginas'])),
                ]),
                TipoUsuario::Logistica => Logistica::create([
                    'usuario_id' => $usuario->id,
                    'nome' => $perfil['nome'],
                    'webhook' => $perfil['webhook'],
                ]),
            };

            if ($endereco !== null) {
                $usuario->enderecos()->create(Arr::only($endereco, [
                    'logradouro', 'numero', 'complemento', 'cidade', 'estado', 'pais', 'cep',
                ]));
            }

            return $usuario->load(['cliente', 'administrador', 'logistica', 'enderecos']);
        });

        $this->emailVerificationService->enviarConfirmacaoInicial($usuario);

        return $usuario;
    }

    public function solicitarTrocaEmail(
        Usuario $usuario,
        string $novoEmail,
    ): void {
        $this->emailVerificationService->solicitarTroca($usuario, $novoEmail);
    }

    public function atualizar(Usuario $usuario, array $dados): Usuario
    {
        if (isset($dados['password'])) {
            $this->politicaSenhaService->alterar($usuario, $dados['password'], [$dados['login'] ?? null]);
            unset($dados['password']);
        }

        if ($dados !== []) {
            $usuario->update($dados);
        }

        return $usuario->refresh();
    }

    public function atualizarCliente(Cliente $cliente, array $dados): Cliente
    {
        DB::transaction(fn () => $this->atualizarDadosDaConta($cliente->usuario, $dados));

        return $cliente->refresh()->load('usuario.enderecos');
    }

    public function atualizarAdministrador(Administrador $administrador, array $dados): Administrador
    {
        DB::transaction(function () use ($administrador, $dados): void {
            if (array_key_exists('paginas', $dados)) {
                $administrador->update(['paginas' => array_values(array_unique($dados['paginas']))]);
            }

            $this->atualizarDadosDaConta($administrador->usuario, $dados);
        });

        return $administrador->refresh()->load('usuario');
    }

    public function atualizarLogistica(Logistica $logistica, array $dados): Logistica
    {
        DB::transaction(function () use ($logistica, $dados): void {
            $logistica->update(Arr::only($dados, ['nome', 'webhook']));
            $this->atualizarDadosDaConta($logistica->usuario, $dados);
        });

        return $logistica->refresh()->load('usuario');
    }

    public function excluirCliente(Cliente $cliente): void
    {

        $this->excluir($cliente->usuario);
    }

    public function excluirAdministrador(Administrador $administrador): void
    {
        $this->excluir($administrador->usuario);
    }

    public function excluirLogistica(Logistica $logistica): void
    {
        $this->excluir($logistica->usuario);
    }

    public function excluir(Usuario $usuario): void
    {
        $this->usuarioExclusaoService->excluir($usuario);
    }

    private function atualizarDadosDaConta(Usuario $usuario, array $dados): void
    {
        $conta = Arr::only($dados, ['login', 'password']);

        if (isset($conta['password'])) {
            $this->politicaSenhaService->alterar($usuario, $conta['password'], [$conta['login'] ?? null]);
            unset($conta['password']);
        }

        if ($conta !== []) {
            $usuario->update($conta);
        }
    }
}
