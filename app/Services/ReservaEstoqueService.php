<?php

namespace App\Services;

use App\Enums\DirecaoMovimentacao;
use App\Models\Carrinho;
use App\Models\CarrinhoItem;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Visitante;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class ReservaEstoqueService
{
    public function contarEstoque(Produto $produto): float
    {
        $entradas = $produto->movimentacoes()
            ->where('direcao', DirecaoMovimentacao::Entrada->value)->sum('quantidade');
        $saidas = $produto->movimentacoes()
            ->where('direcao', DirecaoMovimentacao::Saida->value)->sum('quantidade');

        return round((float) $entradas - (float) $saidas, 3);
    }

    public function contarReservado(int $produtoId, ?string $ignorarDono = null): float
    {
        $chave = $this->chaveProduto($produtoId);
        $reservas = Redis::hgetall($chave);
        $agora = now()->timestamp;
        $total = 0.0;

        foreach ($reservas as $dono => $json) {
            $reserva = is_string($json) ? json_decode($json, true) : null;

            if (! is_array($reserva) || (int) ($reserva['expira_em'] ?? 0) <= $agora) {
                Redis::hdel($chave, $dono);
                continue;
            }

            if ($dono !== $ignorarDono) {
                $total += (float) ($reserva['quantidade'] ?? 0);
            }
        }

        return round($total, 3);
    }

    public function contarDisponivel(Produto $produto): float
    {
        return round($this->contarEstoque($produto) - $this->contarReservado($produto->id), 3);
    }

    /** @param array<int, string> $ignorarDonos */
    public function validarCarrinho(string $dono, Carrinho $carrinho, array $ignorarDonos = []): void
    {
        foreach ($carrinho->itens as $item) {
            $produto = Produto::query()->whereKey($item->codigo)->where('ativo', true)->first();
            $estoque = $produto === null ? 0.0 : $this->contarEstoque($produto);
            $reservadoPorOutros = $this->contarReservadoIgnorando(
                $item->codigo,
                array_merge([$dono], $ignorarDonos),
            );
            $disponivel = round($estoque - $reservadoPorOutros, 3);

            if ($produto === null || $disponivel + 0.0005 < $item->quantidade()) {
                throw ValidationException::withMessages([
                    'itens' => "Estoque disponível insuficiente para o produto {$item->codigo}.",
                ]);
            }
        }
    }

    /** @param array<int, string> $donos */
    private function contarReservadoIgnorando(int $produtoId, array $donos): float
    {
        $reservado = $this->contarReservado($produtoId);
        $chave = $this->chaveProduto($produtoId);

        foreach (array_unique($donos) as $dono) {
            $json = Redis::hget($chave, $dono);
            $reserva = is_string($json) ? json_decode($json, true) : null;
            if (is_array($reserva) && (int) ($reserva['expira_em'] ?? 0) > now()->timestamp) {
                $reservado -= (float) ($reserva['quantidade'] ?? 0);
            }
        }

        return max(0, round($reservado, 3));
    }

    /** Sincroniza o índice por produto; entradas expiradas são descartadas nas consultas. */
    public function sincronizar(Cliente|Visitante $dono, ?Carrinho $anterior, Carrinho $novo, int $expiraEm): void
    {
        $identificador = $this->identificadorDono($dono);
        $itensAnteriores = $anterior?->itens ?? [];
        $codigos = array_unique(array_merge(
            array_map(static fn (CarrinhoItem $item): int => $item->codigo, $itensAnteriores),
            array_map(static fn (CarrinhoItem $item): int => $item->codigo, $novo->itens),
        ));

        foreach ($codigos as $codigo) {
            $chave = $this->chaveProduto($codigo);
            Redis::hdel($chave, $identificador);

            foreach ($novo->itens as $item) {
                if ($item->codigo === $codigo) {
                    Redis::hset($chave, $identificador, json_encode([
                        'quantidade' => $item->quantidade(),
                        'expira_em' => $expiraEm,
                    ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
                    break;
                }
            }
        }
    }

    public function remover(Cliente|Visitante $dono, ?Carrinho $carrinho): void
    {
        if ($carrinho === null) {
            return;
        }

        $identificador = $this->identificadorDono($dono);
        foreach ($carrinho->itens as $item) {
            Redis::hdel($this->chaveProduto($item->codigo), $identificador);
        }
    }

    public function identificadorDono(Cliente|Visitante $dono): string
    {
        return $dono instanceof Cliente ? "cliente:{$dono->id}" : "visitante:{$dono->id}";
    }

    private function chaveProduto(int $produtoId): string
    {
        return "estoque:reservas:produto:{$produtoId}";
    }
}
