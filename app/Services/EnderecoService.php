<?php

namespace App\Services;

use App\Models\Endereco;
use App\Models\Usuario;
use Illuminate\Support\Arr;

class EnderecoService
{
    public function __construct(private readonly ViaCepService $viaCepService) {}

    public function consultar(Endereco $endereco): Endereco
    {
        return $endereco->load('usuario');
    }

    public function criar(Usuario $ator, array $dados): Endereco
    {
        $this->viaCepService->confirmarEndereco($dados);
        $usuarioId = (int) ($dados['usuario_id'] ?? $ator->id);
        unset($dados['usuario_id']);

        $endereco = Usuario::query()->findOrFail($usuarioId)->enderecos()->create(
            Arr::only($dados, ['logradouro', 'numero', 'complemento', 'cidade', 'estado', 'pais', 'cep']),
        );

        return $endereco->load('usuario');
    }

    public function atualizar(Endereco $endereco, array $dados): Endereco
    {
        $this->viaCepService->confirmarEndereco($dados);
        $endereco->update(Arr::only($dados, [
            'logradouro', 'numero', 'complemento', 'cidade', 'estado', 'pais', 'cep',
        ]));

        return $endereco->refresh()->load('usuario');
    }

    public function excluir(Endereco $endereco): void
    {
        $endereco->delete();
    }
}
