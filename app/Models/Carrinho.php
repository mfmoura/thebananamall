<?php

namespace App\Models;

use JsonSerializable;

/**
 * Carrinho armazenado no Redis, não é um model Eloquent.
 * Um carrinho ativo pertence a um Cliente ou a um Visitante e é persistido no Redis.
 */
final class Carrinho implements JsonSerializable
{
    public ?int $reserva_expira_em = null;
    /**
     * @param array<int, CarrinhoItem> $itens
     */
    public function __construct(
        public readonly int $id,
        public readonly ?int $id_cliente,
        public readonly array $itens = [],
        public readonly ?int $id_visitante = null,
    ) {}

    /** @param array<string, mixed> $dados */
    public static function deArray(array $dados): self
    {
        $carrinho = new self(
            (int) $dados['id'],
            isset($dados['id_cliente']) ? (int) $dados['id_cliente'] : null,
            array_map(
                static fn (array $item): CarrinhoItem => CarrinhoItem::deArray($item),
                $dados['itens'] ?? [],
            ),
            isset($dados['id_visitante']) ? (int) $dados['id_visitante'] : null,
        );
        $carrinho->reserva_expira_em = isset($dados['reserva_expira_em']) ? (int) $dados['reserva_expira_em'] : null;

        return $carrinho;
    }

    public static function paraCliente(int $clienteId): self
    {
        return new self($clienteId, $clienteId);
    }

    public static function paraVisitante(int $visitanteId): self
    {
        return new self($visitanteId, null, [], $visitanteId);
    }

    public function adicionarProduto(int $codigo, float $quantidade = 1, ?string $preco = null, ?string $tamanho = null): self
    {
        $itens = $this->itens;

        foreach ($itens as $indice => $item) {
            if ($item->codigo === $codigo && $item->tamanho === $tamanho) {
                $item->alterarQuantidade($item->quantidade() + $quantidade);
                $itens[$indice] = $item;

                return new self($this->id, $this->id_cliente, $itens, $this->id_visitante);
            }
        }

        $itens[] = CarrinhoItem::criar($codigo, $quantidade, $preco, $tamanho);

        return new self($this->id, $this->id_cliente, $itens, $this->id_visitante);
    }

    public function alterarQuantidade(int $codigo, float $quantidade, ?string $tamanho = null): ?self
    {
        foreach ($this->itens as $item) {
            if ($item->codigo === $codigo && $item->tamanho === $tamanho) {
                $item->alterarQuantidade($quantidade);

                return new self($this->id, $this->id_cliente, $this->itens, $this->id_visitante);
            }
        }

        return null;
    }

    public function excluirItem(int $codigo, ?string $tamanho = null): ?self
    {
        $itens = array_values(array_filter(
            $this->itens,
            static fn (CarrinhoItem $item): bool => $item->codigo !== $codigo || $item->tamanho !== $tamanho,
        ));

        if (count($itens) === count($this->itens)) {
            return null;
        }

        return new self($this->id, $this->id_cliente, $itens, $this->id_visitante);
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
                null,
                $item['tamanho'] ?? null,
            ),
            $itens,
        );

        return new self($this->id, $this->id_cliente, array_values($itens), $this->id_visitante);
    }

    public function mesclar(self $outro): self
    {
        $carrinho = $this;

        foreach ($outro->itens as $item) {
            $carrinho = $carrinho->adicionarProduto($item->codigo, $item->quantidade(), $item->preco(), $item->tamanho);
        }

        return $carrinho;
    }

    /** @return array{id: int, id_cliente: ?int, id_visitante: ?int, itens: array<int, array{codigo: int, quantidade: float, data: string, preco: ?string}>} */
    public function toArray(): array
    {
        return [
            'reserva_expira_em' => $this->reserva_expira_em,
            'id' => $this->id,
            'id_cliente' => $this->id_cliente,
            'id_visitante' => $this->id_visitante,
            'itens' => array_map(
                static fn (CarrinhoItem $item): array => $item->toArray(),
                $this->itens,
            ),
        ];
    }

    public function jsonSerialize(): array
    {
        return [
            'reserva_expira_em' => $this->reserva_expira_em,
            'reserva_ativa' => $this->itens !== [] && ($this->reserva_expira_em ?? 0) > now()->timestamp,
            'id' => $this->id,
            'id_cliente' => $this->id_cliente,
            'id_visitante' => $this->id_visitante,
            'itens' => $this->itens,
        ];
    }
}
