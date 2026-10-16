<?php

namespace App\Services;

use App\Enums\NivelAcesso;
use App\Enums\TipoUsuario;
use App\Models\Administrador;
use App\Models\Cliente;
use App\Models\Logistica;
use App\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UsuarioService
{
    public function consultar(Usuario $usuario): Usuario
    {
        return $usuario->load(['cliente', 'administrador', 'logistica', 'enderecos']);
    }

    public function criar(array $dados): Usuario
    {
        return DB::transaction(function () use ($dados): Usuario {
            $tipo = TipoUsuario::from($dados['tipo'] ?? TipoUsuario::Cliente->value);
            $perfil = $dados['perfil'] ?? [];
            $endereco = $dados['endereco'] ?? null;

            unset($dados['tipo'], $dados['perfil'], $dados['endereco']);

            $usuario = Usuario::create($dados);

            match ($tipo) {
                TipoUsuario::Cliente => Cliente::create(['usuario_id' => $usuario->id]),
                TipoUsuario::Administrador => Administrador::create([
                    'usuario_id' => $usuario->id,
                    'nivel_de_acesso' => NivelAcesso::from($perfil['nivel_de_acesso']),
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
    }

    public function atualizar(Usuario $usuario, array $dados): Usuario
    {
        $usuario->update($dados);

        return $usuario->refresh();
    }

    public function excluir(Usuario $usuario): void
    {
        $usuario->tokens()->delete();
        $usuario->delete();
    }
}
