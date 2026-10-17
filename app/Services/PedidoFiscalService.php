<?php

namespace App\Services;

use App\Enums\StatusNotaFiscal;
use App\Enums\StatusPedido;
use App\Jobs\ProcessarPedidoFiscal;
use App\Models\NotaFiscal;
use App\Models\Pedido;
use App\Models\PedidoProcessamentoFiscal;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PedidoFiscalService
{
    public const PAGOS = ['pago', 'em_processamento', 'enviado', 'entregue'];

    public function __construct(private readonly ContoraNotaFiscalService $contora, private readonly ViaCepService $viaCep) {}

    public function acompanhar(Pedido $pedido, array $destinatario): PedidoProcessamentoFiscal
    {
        return PedidoProcessamentoFiscal::query()->firstOrCreate(['pedido_id' => $pedido->id], [
            'chave' => (string) Str::uuid(), 'dados_cliente' => $destinatario,
            'etapa' => 'aguardando_pagamento', 'proxima_tentativa' => now(),
        ]);
    }

    public function enfileirar(Pedido $pedido): void
    {
        $processamento = $pedido->processamentoFiscal;
        if (! $processamento || ! in_array($pedido->status->value, self::PAGOS, true)
            || in_array($processamento->etapa, ['concluido', 'cancelado', 'falhou'], true)) {
            return;
        }
        $processamento->update(['etapa' => 'pendente', 'proxima_tentativa' => now()->addMinutes(5)]);
        try {
            ProcessarPedidoFiscal::dispatch($processamento->id)->afterCommit();
        } catch (\Throwable $exception) {
            // O registro durável será reenfileirado pelo scheduler quando o Redis voltar.
            $processamento->update(['proxima_tentativa' => now()]);
            try {
                app(UniqueLock::class)->release(new ProcessarPedidoFiscal($processamento->id));
            } catch (\Throwable) {
                // A indisponibilidade do cache não pode desfazer um pagamento já confirmado.
            }
            report($exception);
        }
    }

    public function retomarPendentes(): int
    {
        $total = 0;
        PedidoProcessamentoFiscal::query()->whereIn('etapa', ['aguardando_pagamento', 'pendente', 'processando', 'aguardando_configuracao'])
            ->where('proxima_tentativa', '<=', now())->whereHas('pedido', fn ($query) => $query->whereIn('status', self::PAGOS))
            ->with('pedido')->chunkById(100, function ($processamentos) use (&$total): void {
                foreach ($processamentos as $processamento) {
                    $this->enfileirar($processamento->pedido);
                    $total++;
                }
            });

        return $total;
    }

    public function baixarDanfe(Pedido $pedido): StreamedResponse
    {
        $processamento = $pedido->processamentoFiscal;
        if ($processamento?->etapa !== 'concluido' || ! $processamento->notaFiscal) {
            throw new HttpException(409, 'Seus dados estão sendo processados.');
        }

        return $this->contora->baixar($processamento->notaFiscal, 'danfe');
    }

    /** Retorna o atraso de nova consulta, ou null quando este job pode terminar. */
    public function processar(int $id): ?int
    {
        $processamento = PedidoProcessamentoFiscal::query()->with(['pedido.cliente.usuario', 'pedido.itens.produto', 'pedido.formaPagamento'])->findOrFail($id);
        $pedido = $processamento->pedido;
        if (in_array($processamento->etapa, ['concluido', 'cancelado'], true)) {
            return null;
        }
        if ($pedido->status === StatusPedido::Cancelado) {
            $processamento->update(['etapa' => 'cancelado']);

            return null;
        }
        if (! in_array($pedido->status->value, self::PAGOS, true)) {
            $processamento->update(['etapa' => 'aguardando_pagamento']);

            return null;
        }
        $processamento->update(['etapa' => 'processando', 'proxima_tentativa' => now()->addMinutes(10)]);
        try {
            if ($processamento->dados_emissao === null) {
                $dados = $this->montarDados($processamento);
                if ($dados === null) {
                    return null;
                }
                $processamento->update(['dados_emissao' => $dados]);
            }
            $nota = $processamento->nota_fiscal_id
                ? NotaFiscal::query()->findOrFail($processamento->nota_fiscal_id)
                : $this->contora->emitir($pedido->cliente->usuario, $processamento->dados_emissao);
            $processamento->update(['nota_fiscal_id' => $nota->id]);
            $nota = $this->contora->sincronizar($nota);
            if ($nota->estaAutorizada() && $nota->caminho_danfe) {
                $processamento->update(['etapa' => 'concluido', 'ultimo_erro' => null]);

                return null;
            }
            if (in_array($nota->status, [StatusNotaFiscal::Rejeitada, StatusNotaFiscal::Erro, StatusNotaFiscal::Cancelada], true)) {
                $processamento->update(['etapa' => 'falhou', 'ultimo_erro' => 'Verifique a nota e o retorno fiscal no painel administrativo.']);

                return null;
            }
            $processamento->update(['proxima_tentativa' => now()->addMinutes(7)]);

            return 60;
        } catch (ValidationException $exception) {
            $this->aguardarConfiguracao($processamento, 'Confira os dados fiscais do destinatário e dos produtos.');

            return null;
        } catch (\Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface && in_array($exception->getStatusCode(), [409, 422], true)) {
                $processamento->update(['etapa' => 'falhou', 'ultimo_erro' => 'Confira a emissão original e os dados no painel fiscal.']);

                return null;
            }
            throw $exception;
        }
    }

    private function montarDados(PedidoProcessamentoFiscal $processamento): ?array
    {
        $pedido = $processamento->pedido;
        $uf = (string) config('services.fiscal_contora.uf_emitente');
        $cnpj = (string) config('services.fiscal_contora.cnpj');
        if (! $processamento->dados_cliente || ! preg_match('/^[A-Z]{2}$/', $uf) || ! preg_match('/^\d{14}$/', $cnpj)) {
            $this->aguardarConfiguracao($processamento, 'Configure o emitente fiscal e os dados de faturamento do pedido.');

            return null;
        }
        $itens = [];
        foreach ($pedido->itens as $item) {
            $configuracao = $item->configuracao_fiscal ?? $item->produto?->configuracao_fiscal;
            if (! $configuracao || ! isset($configuracao['ncm'], $configuracao['cfop_interno'], $configuracao['cfop_interestadual'], $configuracao['unidade'], $configuracao['tributos']['icms'])) {
                $this->aguardarConfiguracao($processamento, 'Complete a configuração fiscal dos produtos do pedido.');

                return null;
            }
            if ($item->configuracao_fiscal === null) {
                $item->update(['configuracao_fiscal' => $configuracao]);
            }
            $itens[] = [
                'produto_id' => $item->codigo, 'quantidade' => (float) $item->quantidade, 'valor_unitario' => (float) $item->preco,
                'ncm' => $configuracao['ncm'], 'unidade_comercial' => $configuracao['unidade'], 'tributos' => $configuracao['tributos'],
                'cfop' => $processamento->dados_cliente['endereco']['estado'] === $uf ? $configuracao['cfop_interno'] : $configuracao['cfop_interestadual'],
            ];
        }
        $meio = match ($pedido->formaPagamento->codigo) {
            'pix' => '17', 'boleto' => '15', 'credito', 'cartao_credito' => '03', 'debito', 'cartao_debito' => '04', default => null,
        };
        if ($meio === null) {
            $this->aguardarConfiguracao($processamento, 'Configure o código fiscal da forma de pagamento.');

            return null;
        }
        // Consulta externa fora da requisição de compra e fora de transações de estoque.
        $this->viaCep->confirmarEndereco($processamento->dados_cliente['endereco'], 'dados_fiscais.endereco');

        return [
            'idempotency_key' => $processamento->chave, 'pedido_id' => $pedido->id, 'natureza_operacao' => 'venda',
            'numero' => $this->proximoNumero($cnpj), 'serie' => (int) config('services.fiscal_contora.serie', 1),
            'consumidor_final' => true, 'finalidade' => 1, 'destinatario' => $processamento->dados_cliente,
            'pagamentos' => [['meio' => $meio, 'valor' => (float) $pedido->total]], 'itens' => $itens,
        ];
    }

    private function proximoNumero(string $cnpj): int
    {
        $serie = (int) config('services.fiscal_contora.serie', 1);
        if ($serie < 1 || $serie > 889 || config('services.fiscal_contora.ambiente') !== 'homologacao') {
            throw new HttpException(503, 'Configure série e ambiente de homologação.');
        }

        return DB::transaction(function () use ($cnpj, $serie): int {
            $identidade = ['cnpj' => $cnpj, 'ambiente' => 'homologacao', 'serie' => $serie];
            DB::table('sequencias_fiscais')->insertOrIgnore([...$identidade, 'ultimo_numero' => 0]);
            $sequencia = DB::table('sequencias_fiscais')->where($identidade)->lockForUpdate()->first();
            $existente = NotaFiscal::query()->where('provedor', 'contora')->where('emitente_documento', $cnpj)
                ->where('ambiente', 'homologacao')->where('serie', (string) $serie)->max(DB::raw('CAST(numero AS BIGINT)'));
            $numero = max((int) $sequencia->ultimo_numero, (int) $existente) + 1;
            DB::table('sequencias_fiscais')->where('id', $sequencia->id)->update(['ultimo_numero' => $numero]);

            return $numero;
        });
    }

    private function aguardarConfiguracao(PedidoProcessamentoFiscal $processamento, string $motivo): void
    {
        $processamento->update(['etapa' => 'aguardando_configuracao', 'ultimo_erro' => $motivo, 'proxima_tentativa' => now()->addMinutes(5)]);
    }
}
