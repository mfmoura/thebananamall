<?php

namespace App\Services;

use App\Enums\DirecaoMovimentacao;
use App\Enums\NaturezaOperacaoNotaFiscal;
use App\Enums\TipoOperacaoNotaFiscal;
use App\Models\NotaEstoque;
use App\Models\NotaFiscal;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProdutoMovimentacaoService
{
    public function __construct(
        private readonly ReservaEstoqueService $reservaEstoqueService,
        private readonly CarrinhoService $carrinhoService,
    ) {}

    public function contarEstoque(Produto $produto): float
    {
        return $this->reservaEstoqueService->contarEstoque($produto);
    }

    public function contarDisponivel(Produto $produto): float
    {
        return $this->carrinhoService->comBloqueioDeEstoque(
            fn (): float => $this->reservaEstoqueService->contarDisponivel($produto),
        );
    }

    /** @return array{estoque: float, estoque_reservado: float, estoque_disponivel: float} */
    public function resumoEstoque(Produto $produto): array
    {
        return $this->carrinhoService->comBloqueioDeEstoque(function () use ($produto): array {
            $contado = $this->reservaEstoqueService->contarEstoque($produto);
            $reservado = $this->reservaEstoqueService->contarReservado($produto->id);

            return [
                'estoque' => $contado,
                'estoque_reservado' => $reservado,
                'estoque_disponivel' => round($contado - $reservado, 3),
            ];
        });
    }

    public function contarReservado(int $produtoId): float
    {
        return $this->reservaEstoqueService->contarReservado($produtoId);
    }

    public function registrarManual(Usuario $usuario, array $dados): ProdutoMovimentacao
    {
        return $this->carrinhoService->comBloqueioDeEstoque(fn (): ProdutoMovimentacao => DB::transaction(
            function () use ($usuario, $dados): ProdutoMovimentacao {
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
            },
        ));
    }

    /** Deve ser chamado dentro do bloqueio Redis e da transacao de registro da nota. */
    public function registrarDaNotaFiscal(NotaFiscal $nota, Usuario $usuario): void
    {
        if (! $nota->estaAutorizada()) {
            throw ValidationException::withMessages([
                'nota_fiscal' => 'Somente notas fiscais autorizadas movimentam o estoque.',
            ]);
        }

        if ($nota->ambiente === 'homologacao') {
            return;
        }

        /** @var NaturezaOperacaoNotaFiscal $natureza */
        $natureza = $nota->natureza_operacao;
        $direcao = $natureza->tipoOperacao() === TipoOperacaoNotaFiscal::Entrada
            ? DirecaoMovimentacao::Entrada
            : DirecaoMovimentacao::Saida;

        // O checkout ja registra a saida ligada ao pedido; emitir a NF de venda nao pode abater de novo.
        if ($natureza === NaturezaOperacaoNotaFiscal::Venda && $nota->pedido_id !== null) {
            return;
        }

        $notaEstoque = NotaEstoque::query()->create([
            'nota_fiscal_id' => $nota->id,
            'usuario_id' => $usuario->id,
            'referencia' => 'NF-'.$nota->id,
            'observacao' => 'Movimentacao originada pela nota fiscal '.$nota->numero.'.',
        ]);

        foreach ($nota->itens->groupBy('produto_id')->sortKeys() as $produtoId => $itens) {
            $produto = Produto::query()->lockForUpdate()->findOrFail($produtoId);
            $quantidade = (float) $itens->sum(static fn ($item): float => (float) $item->quantidade);

            $this->validarSaida($produto, $direcao, $quantidade);
            $this->criarMovimentacao($produto, $notaEstoque, $direcao, $quantidade);
        }
    }

    public function registrarSaidaDoPedido(Produto $produto, Pedido $pedido, float $quantidade): ProdutoMovimentacao
    {
        $nota = NotaEstoque::query()->firstOrCreate(
            ['pedido_id' => $pedido->id],
            ['referencia' => 'PEDIDO-'.$pedido->id, 'observacao' => 'Reserva de estoque do pedido.'],
        );

        // O checkout consome a quantidade já reservada pelo carrinho do próprio Cliente.
        $this->validarSaida($produto, DirecaoMovimentacao::Saida, $quantidade, false);

        return $this->criarMovimentacao($produto, $nota, DirecaoMovimentacao::Saida, $quantidade);
    }

    public function estornar(ProdutoMovimentacao $movimentacao, ?int $usuarioId): bool
    {
        return $this->carrinhoService->comBloqueioDeEstoque(fn (): bool => DB::transaction(
            function () use ($movimentacao, $usuarioId): bool {
                $original = ProdutoMovimentacao::query()->lockForUpdate()->findOrFail($movimentacao->id);

                return $this->estornarDentroDaTransacao($original, $usuarioId);
            },
        ));
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

    private function validarSaida(
        Produto $produto,
        DirecaoMovimentacao $direcao,
        float $quantidade,
        bool $descontarReservas = true,
    ): void
    {
        $estoque = $descontarReservas
            ? $this->reservaEstoqueService->contarDisponivel($produto)
            : $this->contarEstoque($produto);

        if (
            $direcao === DirecaoMovimentacao::Saida
            && (int) round($estoque * 1000) < (int) round($quantidade * 1000)
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
