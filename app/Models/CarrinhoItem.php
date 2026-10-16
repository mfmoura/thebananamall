<?php

namespace App\Models;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Item de domínio persistido dentro do carrinho no Redis; codigo referencia um Produto.
 */
final class CarrinhoItem implements JsonSerializable
{
    private bool $modificado;
    public readonly int $codigo;
    private float $quantidade;
    private string $data;

    public function __construct(
        int $codigo,
        float $quantidade,
        ?string $data = null,
        bool $modificado = false,
    ) {
        $this->validarQuantidade($quantidade);
        $this->codigo = $codigo;
        $this->quantidade = $quantidade;
        $this->data = $data ?? now()->toDateString();
        $this->modificado = $modificado;
    }

    public static function criar(int $codigo, float $quantidade): self
    {
        return new self($codigo, $quantidade, now()->toDateString(), true);
    }

    /** @param array{codigo: int|string, quantidade: int|float|string, data?: string} $dados */
    public static function deArray(array $dados): self
    {
        return new self(
            (int) $dados['codigo'],
            (float) $dados['quantidade'],
            $dados['data'] ?? now()->toDateString(),
        );
    }

    public function quantidade(): float
    {
        return $this->quantidade;
    }

    public function alterarQuantidade(float $quantidade): float
    {
        $this->validarQuantidade($quantidade);

        if ($this->quantidade !== $quantidade) {
            $this->quantidade = $quantidade;
            $this->data = now()->toDateString();
            $this->modificado = true;
        }

        return $this->quantidade;
    }

    public function alertarModificacao(): bool
    {
        return $this->modificado;
    }

    /** @return array{codigo: int, quantidade: float, data: string} */
    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'quantidade' => $this->quantidade,
            'data' => $this->data,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray() + ['modificado' => $this->alertarModificacao()];
    }

    private function validarQuantidade(float $quantidade): void
    {
        if (! is_finite($quantidade) || $quantidade <= 0) {
            throw new InvalidArgumentException('A quantidade do item deve ser maior que zero.');
        }
    }
}
