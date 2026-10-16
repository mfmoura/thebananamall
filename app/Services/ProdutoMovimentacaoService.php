<?php

namespace App\Services;

use App\Enums\DirecaoMovimentacao;
use App\Models\NotaEstoque;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProdutoMovimentacaoService
{
    public function contarEstoque(Produto $produto): float
    {
        $entradas = $produto->movimentacoes()->where('direcao', DirecaoMovimentacao::Entrada->value)->sum('quantidade');
        $saidas = $produto->movimentacoes()->where('direcao', DirecaoMovimentacao::Saida->value)->sum('quantidade');

        return round((float) $entradas - (float) $saidas, 3);
    }

    public function registrarManual(Usuario $usuario, array $dados): ProdutoMovimentacao
    {
        return DB::transaction(function () use ($usuario, $dados): ProdutoMovimentacao {
            $produto = Produto::query()->lockForUpdate()->findOrFail($dados['produto_id']);
            $direcao = DirecaoMovimentacao::from($dados['direcao']);
            $quantidade = (float) $dados['quantidade'];

            $this->validarSaida($produto, $direcao, $quantidade);

            $nota = NotaEstoque::query()->create([
                'usuario_id' => $usuario->id,
                'referencia' => $dados['nota'],
                'observacao' => $dados['observacao'] ?? null,
            ]);

            return $this->criarMovimentacao($produto, $nota, $direcao, $quantidade);
        });
    }

    public function registrarSaidaDoPedido(Produto $produto, Pedido $pedido, float $quantidade): ProdutoMovimentacao
    {
        $nota = NotaEstoque::query()->firstOrCreate(
            ['pedido_id' => $pedido->id],
            ['referencia' => 'PEDIDO-'.$pedido->id, 'observacao' => 'Reserva de estoque do pedido.'],
        );

        $this->validarSaida($produto, DirecaoMovimentacao::Saida, $quantidade);

        return $this->criarMovimentacao($produto, $nota, DirecaoMovimentacao::Saida, $quantidade);
    }

    public function estornar(ProdutoMovimentacao $movimentacao, ?int $usuarioId): bool
    {
        return DB::transaction(function () use ($movimentacao, $usuarioId): bool {
            $original = ProdutoMovimentacao::query()->lockForUpdate()->findOrFail($movimentacao->id);

            return $this->estornarDentroDaTransacao($original, $usuarioId);
        });
    }

    public function estornarSaidasDoPedido(Pedido $pedido): void
    {
        $nota = NotaEstoque::query()->where('pedido_id', $pedido->id)->first();

        if ($nota === null) {
            return;
        }

        foreach ($nota->movimentacoes()->whereNull('estorno_de_id')->lockForUpdate()->get() as $movimentacao) {
            $this->estornarDentroDaTransacao($movimentacao, null);
        }
    }

    private function estornarDentroDaTransacao(ProdutoMovimentacao $original, ?int $usuarioId): bool
    {
        if ($original->estorno_de_id !== null || ProdutoMovimentacao::query()->where('estorno_de_id', $original->id)->exists()) {
            return false;
        }

        $produto = Produto::query()->lockForUpdate()->findOrFail($original->produto_id);
        $direcaoEstorno = $original->direcao->oposta();
        $quantidade = (float) $original->quantidade;
        $this->validarSaida($produto, $direcaoEstorno, $quantidade);

        $notaEstorno = NotaEstoque::query()->create([
            'usuario_id' => $usuarioId,
            'referencia' => 'ESTORNO-'.$original->id,
            'observacao' => 'Estorno da movimentação '.$original->id,
        ]);

        $this->criarMovimentacao($produto, $notaEstorno, $direcaoEstorno, $quantidade, $original->id);

        return true;
    }

    private function validarSaida(Produto $produto, DirecaoMovimentacao $direcao, float $quantidade): void
    {
        if (
            $direcao === DirecaoMovimentacao::Saida
            && (int) round($this->contarEstoque($produto) * 1000) < (int) round($quantidade * 1000)
        ) {
            throw ValidationException::withMessages([
                'quantidade' => 'Estoque insuficiente para registrar esta saída.',
            ]);
        }
    }

    private function criarMovimentacao(
        Produto $produto,
        NotaEstoque $nota,
        DirecaoMovimentacao $direcao,
        float $quantidade,
        ?int $estornoDeId = null,
    ): ProdutoMovimentacao {
        return $produto->movimentacoes()->create([
            'nota_id' => $nota->id,
            'direcao' => $direcao,
            'quantidade' => $quantidade,
            'data' => now(),
            'estorno_de_id' => $estornoDeId,
        ]);
    }
}
