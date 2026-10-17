<?php

namespace App\Models;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Item de domínio persistido dentro do carrinho no Redis; codigo referencia um Produto.
 */
final class CarrinhoItem implements JsonSerializable
{
    public readonly int $codigo;
    private float $quantidade;
    private string $data;
    private ?string $preco;
    private ?string $precoAtual = null;
    private ?string $nomeProduto = null;
    private ?string $imagemPrincipal = null;

    public function __construct(
        int $codigo,
        float $quantidade,
        ?string $data = null,
        ?string $preco = null,
    ) {
        $this->validarQuantidade($quantidade);
        $this->codigo = $codigo;
        $this->quantidade = $quantidade;
        $this->data = $data ?? now()->toDateString();
        $this->preco = $preco;
    }

    public static function criar(int $codigo, float $quantidade, ?string $preco = null): self
    {
        return new self($codigo, $quantidade, now()->toDateString(), $preco);
    }

    /** @param array{codigo: int|string, quantidade: int|float|string, data?: string} $dados */
    public static function deArray(array $dados): self
    {
        return new self(
            (int) $dados['codigo'],
            (float) $dados['quantidade'],
            $dados['data'] ?? now()->toDateString(),
            isset($dados['preco']) ? (string) $dados['preco'] : null,
        );
    }

    public function definirPrecoAtual(?string $preco): void
    {
        $this->precoAtual = $preco;
    }

    public function definirDadosProduto(?string $nome, ?string $imagemPrincipal): void
    {
        $this->nomeProduto = $nome;
        $this->imagemPrincipal = $imagemPrincipal;
    }

    public function quantidade(): float
    {
        return $this->quantidade;
    }

    public function preco(): ?string
    {
        return $this->preco;
    }

    public function alterarQuantidade(float $quantidade): float
    {
        $this->validarQuantidade($quantidade);

        if ($this->quantidade !== $quantidade) {
            $this->quantidade = $quantidade;
            $this->data = now()->toDateString();
        }

        return $this->quantidade;
    }

    public function alertarModificacao(): bool
    {
        return $this->preco !== null
            && $this->precoAtual !== null
            && number_format((float) $this->preco, 2, '.', '') !== number_format((float) $this->precoAtual, 2, '.', '');
    }

    /** @return array{codigo: int, quantidade: float, data: string, preco: ?string} */
    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'quantidade' => $this->quantidade,
            'data' => $this->data,
            'preco' => $this->preco,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray() + [
            'preco_atual' => $this->precoAtual,
            'modificado' => $this->alertarModificacao(),
            'nome' => $this->nomeProduto,
            'imagem_principal' => $this->imagemPrincipal,
        ];
    }

    private function validarQuantidade(float $quantidade): void
    {
        if (! is_finite($quantidade) || $quantidade <= 0) {
            throw new InvalidArgumentException('A quantidade do item deve ser maior que zero.');
        }
    }
}
