<?php

namespace App\Services;

use App\Enums\StatusPedido;
use App\Models\Pedido;
use App\Models\PedidoPagamento;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PedidoAdminService
{
    public function listar(array $filtros): LengthAwarePaginator
    {
        return Pedido::query()->with(['itens', 'formaPagamento', 'processamentoFiscal'])
            ->when($filtros['pedido_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($filtros['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id')->paginate($filtros['per_page'] ?? 10);
    }

    public function buscar(Pedido $pedido): array
    {
        $pedido->load(['itens.produto', 'formaPagamento', 'processamentoFiscal', 'pagamento']);

        return [
            'pedido' => $pedido,
            'pode_cancelar' => $pedido->status === StatusPedido::AguardandoPagamento
                && ! PedidoPagamento::withTrashed()->where('pedido_id', $pedido->id)->exists(),
            'danfe_disponivel' => $pedido->processamentoFiscal?->etapa === 'concluido',
            'etiqueta_disponivel' => in_array($pedido->status->value, PedidoFiscalService::PAGOS, true)
                && ! empty($pedido->processamentoFiscal?->dados_cliente['endereco']),
        ];
    }

    public function etiqueta(Pedido $pedido): array
    {
        abort_unless(in_array($pedido->status->value, PedidoFiscalService::PAGOS, true), 409, 'A etiqueta exige um pedido pago.');
        $dados = $pedido->processamentoFiscal?->dados_cliente;
        abort_unless(! empty($dados['nome']) && ! empty($dados['endereco']), 409, 'Endereço do pedido indisponível.');

        return ['pedido_id' => $pedido->id, 'nome' => $dados['nome'], 'endereco' => $dados['endereco']];
    }
}
