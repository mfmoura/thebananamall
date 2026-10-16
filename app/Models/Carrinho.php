<?php

namespace App\Models;

use JsonSerializable;

/**
 * Carrinho armazenado no Redis, não é um model Eloquent.
 * O ID do carrinho é o ID do Cliente, pois cada cliente possui um carrinho ativo.
 */
final class Carrinho implements JsonSerializable
{
    /**
     * @param array<int, CarrinhoItem> $itens
     */
    public function __construct(
        public readonly int $id,
        public readonly int $id_cliente,
        public readonly array $itens = [],
    ) {}

    /** @param array<string, mixed> $dados */
    public static function deArray(array $dados): self
    {
        return new self(
            (int) $dados['id'],
            (int) $dados['id_cliente'],
            array_map(
                static fn (array $item): CarrinhoItem => CarrinhoItem::deArray($item),
                $dados['itens'] ?? [],
            ),
        );
    }

    public function adicionarProduto(int $codigo, float $quantidade = 1): self
    {
        $itens = $this->itens;

        foreach ($itens as $indice => $item) {
            if ($item->codigo === $codigo) {
                $item->alterarQuantidade($item->quantidade() + $quantidade);
                $itens[$indice] = $item;

                return new self($this->id, $this->id_cliente, $itens);
            }
        }

        $itens[] = CarrinhoItem::criar($codigo, $quantidade);

        return new self($this->id, $this->id_cliente, $itens);
    }

    public function alterarQuantidade(int $codigo, float $quantidade): ?self
    {
        foreach ($this->itens as $item) {
            if ($item->codigo === $codigo) {
                $item->alterarQuantidade($quantidade);

                return new self($this->id, $this->id_cliente, $this->itens);
            }
        }

        return null;
    }

    public function excluirItem(int $codigo): ?self
    {
        $itens = array_values(array_filter(
            $this->itens,
            static fn (CarrinhoItem $item): bool => $item->codigo !== $codigo,
        ));

        if (count($itens) === count($this->itens)) {
            return null;
        }

        return new self($this->id, $this->id_cliente, $itens);
    }

    /**
     * @param array<int, array{codigo: int|string, quantidade: int|float|string}> $itens
     */
    public function substituirProdutos(array $itens): self
    {
        $itens = array_map(
            static fn (array $item): CarrinhoItem => CarrinhoItem::criar(
                (int) $item['codigo'],
                (float) $item['quantidade'],
            ),
            $itens,
        );

        return new self($this->id, $this->id_cliente, array_values($itens));
    }

    /** @return array{id: int, id_cliente: int, itens: array<int, array{codigo: int, quantidade: float, data: string}>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'id_cliente' => $this->id_cliente,
            'itens' => array_map(
                static fn (CarrinhoItem $item): array => $item->toArray(),
                $this->itens,
            ),
        ];
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'id_cliente' => $this->id_cliente,
            'itens' => $this->itens,
        ];
    }
}
