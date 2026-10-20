<?php

namespace App\Services;

use App\Enums\NaturezaOperacaoNotaFiscal;
use App\Enums\StatusNotaFiscal;
use App\Models\NotaFiscal;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotaFiscalService
{
    public function __construct(
        private readonly CarrinhoService $carrinhoService,
        private readonly ProdutoMovimentacaoService $movimentacaoService,
        private readonly GradeProdutoService $grades,
    ) {}

    public function registrar(Usuario $usuario, array $dados): NotaFiscal
    {
        return $this->persistir($usuario, $dados, StatusNotaFiscal::Autorizada);
    }

    public function prepararEmissao(Usuario $usuario, array $dados, array $integracao): NotaFiscal
    {
        return $this->persistir($usuario, $dados, StatusNotaFiscal::Pendente, $integracao);
    }

    private function persistir(Usuario $usuario, array $dados, StatusNotaFiscal $status, array $integracao = []): NotaFiscal
    {
        $natureza = NaturezaOperacaoNotaFiscal::from($dados['natureza_operacao']);

        if (($dados['pedido_id'] ?? null) !== null && $natureza !== NaturezaOperacaoNotaFiscal::Venda) {
            throw ValidationException::withMessages([
                'pedido_id' => 'Somente uma nota de venda pode estar vinculada a um pedido.',
            ]);
        }

        return $this->carrinhoService->comBloqueioDeEstoque(fn (): NotaFiscal => DB::transaction(
            function () use ($usuario, $dados, $natureza, $status, $integracao): NotaFiscal {
                $produtosIds = collect($dados['itens'])->pluck('produto_id')->unique()->sort()->values();
                $produtos = Produto::query()->whereIn('id', $produtosIds)->orderBy('id')
                    ->lockForUpdate()->get()->keyBy('id');

                if ($produtos->count() !== $produtosIds->count()) {
                    throw ValidationException::withMessages(['itens' => 'Um ou mais produtos não existem.']);
                }

                $pedido = null;
                if (($dados['pedido_id'] ?? null) !== null) {
                    $pedido = Pedido::query()->with('itens')->lockForUpdate()->findOrFail($dados['pedido_id']);
                    $this->validarItensDaVenda($dados['itens'], $pedido);
                }

                $itens = [];
                $totalCentavos = 0;
                foreach ($dados['itens'] as $item) {
                    /** @var Produto $produto */
                    $produto = $produtos->get((int) $item['produto_id']);
                    $quantidade = (float) $item['quantidade'];
                    $this->grades->validarTamanho($produto, $item['tamanho'] ?? null, $quantidade);
                    $valorUnitario = (float) $item['valor_unitario'];
                    $valorTotal = isset($item['valor_total'])
                        ? (float) $item['valor_total']
                        : round($quantidade * $valorUnitario, 2);
                    $totalCentavos += (int) round($valorTotal * 100);
                    $itens[] = [
                        ...$item,
                        'codigo_produto' => $item['codigo_produto'] ?? $produto->codigo,
                        'descricao' => $item['descricao'] ?? ($produto->nome.(isset($item['tamanho']) ? ' - Tamanho '.$item['tamanho'] : '')),
                        'valor_total' => number_format($valorTotal, 2, '.', ''),
                    ];
                }

                $totalProdutos = $totalCentavos / 100;
                $frete = (float) ($dados['total_frete'] ?? 0);
                $desconto = (float) ($dados['total_desconto'] ?? 0);
                $impostos = (float) ($dados['total_impostos'] ?? 0);
                $nota = NotaFiscal::query()->create([
                    'pedido_id' => $pedido?->id,
                    'provedor' => ($dados['provedor'] ?? null) ?: 'manual',
                    'referencia_provedor' => $dados['referencia_provedor'],
                    'chave_acesso' => $dados['chave_acesso'] ?? null,
                    'numero' => $dados['numero'],
                    'serie' => $dados['serie'] ?? null,
                    'status' => $status,
                    'tipo_operacao' => $natureza->tipoOperacao(),
                    'natureza_operacao' => $natureza,
                    'data_emissao' => $dados['data_emissao'],
                    'data_autorizacao' => $status === StatusNotaFiscal::Autorizada ? ($dados['data_autorizacao'] ?? now()) : null,
                    'caminho_xml' => $dados['caminho_xml'] ?? null,
                    'caminho_danfe' => $dados['caminho_danfe'] ?? null,
                    'total_produtos' => number_format($totalProdutos, 2, '.', ''),
                    'total_frete' => number_format($frete, 2, '.', ''),
                    'total_desconto' => number_format($desconto, 2, '.', ''),
                    'total_impostos' => number_format($impostos, 2, '.', ''),
                    'valor_total' => number_format($totalProdutos + $frete + $impostos - $desconto, 2, '.', ''),
                ]);

                $nota->itens()->createMany($itens);
                if ($integracao !== []) {
                    $nota->forceFill($integracao)->save();
                }
                if ($status === StatusNotaFiscal::Autorizada) {
                    $this->movimentacaoService->registrarDaNotaFiscal($nota->load('itens'), $usuario);
                }

                return $nota->load(['itens', 'notaEstoque.movimentacoes']);
            },
        ));
    }

    /** @param array<int, array{produto_id:int|string, quantidade:int|float|string}> $itens */
    private function validarItensDaVenda(array $itens, Pedido $pedido): void
    {
        $quantidadesPedido = $pedido->itens->groupBy(fn ($item) => $item->codigo.':'.$item->tamanho)->map(
            static fn ($linhas): float => (float) $linhas->sum('quantidade'),
        );
        $quantidadesNota = collect($itens)->groupBy(fn ($item) => $item['produto_id'].':'.($item['tamanho'] ?? ''))->map(
            static fn ($linhas): float => (float) $linhas->sum('quantidade'),
        );

        foreach ($quantidadesNota as $produtoId => $quantidade) {
            if ($quantidade > (float) $quantidadesPedido->get($produtoId, 0)) {
                throw ValidationException::withMessages([
                    'itens' => 'A nota de venda não pode exceder os produtos e quantidades do pedido.',
                ]);
            }
        }
    }
}
