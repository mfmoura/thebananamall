<?php

namespace App\Services;

use App\Models\Carrinho;
use App\Models\Cliente;
use Illuminate\Support\Facades\Redis;

class CarrinhoService
{
    public function consultar(Cliente $cliente): ?Carrinho
    {
        $json = Redis::get($this->chave($cliente));

        if ($json === null || $json === false) {
            return null;
        }

        return Carrinho::deArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    }

    public function criar(Cliente $cliente): Carrinho
    {
        return $this->consultar($cliente) ?? $this->salvar(
            $cliente,
            new Carrinho($cliente->id, $cliente->id),
        );
    }

    /**
     * @param array<int, array{codigo: int|string, quantidade: int|float|string}> $itens
     */
    public function atualizar(Cliente $cliente, array $itens): ?Carrinho
    {
        $carrinho = $this->consultar($cliente);

        if ($carrinho === null) {
            return null;
        }

        return $this->salvar($cliente, $carrinho->substituirProdutos($itens));
    }

    public function adicionarProduto(Cliente $cliente, int $codigo, float $quantidade = 1): Carrinho
    {
        $carrinho = $this->consultar($cliente) ?? new Carrinho($cliente->id, $cliente->id);

        return $this->salvar($cliente, $carrinho->adicionarProduto($codigo, $quantidade));
    }

    public function atualizarItem(Cliente $cliente, int $codigo, float $quantidade): ?Carrinho
    {
        $carrinho = $this->consultar($cliente);
        $carrinhoAtualizado = $carrinho?->alterarQuantidade($codigo, $quantidade);

        if ($carrinhoAtualizado === null) {
            return null;
        }

        return $this->salvar($cliente, $carrinhoAtualizado);
    }

    public function excluirItem(Cliente $cliente, int $codigo): bool
    {
        $carrinho = $this->consultar($cliente);
        $carrinhoAtualizado = $carrinho?->excluirItem($codigo);

        if ($carrinhoAtualizado === null) {
            return false;
        }

        $this->salvar($cliente, $carrinhoAtualizado);

        return true;
    }

    public function excluir(Cliente $cliente): bool
    {
        return Redis::del($this->chave($cliente)) > 0;
    }

    private function salvar(Cliente $cliente, Carrinho $carrinho): Carrinho
    {
        Redis::setex(
            $this->chave($cliente),
            (int) config('carrinho.ttl'),
            json_encode($carrinho->toArray(), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        );

        return $carrinho;
    }

    private function chave(Cliente $cliente): string
    {
        return "carrinho:cliente:{$cliente->id}";
    }
}
