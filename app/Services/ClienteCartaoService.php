<?php

namespace App\Services;

use App\Enums\StatusClienteCartao;
use App\Models\Cliente;
use App\Models\ClienteCartao;
use Illuminate\Database\Eloquent\Collection;

class ClienteCartaoService
{
    public function listar(Cliente $cliente): Collection
    {
        return $cliente->cartoes()->latest()->get();
    }

    /**
     * Registra somente os metadados e as referências devolvidas pelo gateway.
     * Nunca passe PAN, CVV ou token temporário de captura neste método.
     *
     * @param array{apelido: string, tipo: string, validade: string, bandeira: string, ultimos_quatro: string, gateway: string, referencia_cliente_gateway: string, referencia_cartao_gateway: string} $dados
     */
    public function registrarTokenizado(Cliente $cliente, array $dados): ClienteCartao
    {
        return $cliente->cartoes()->create(array_merge($dados, [
            'status' => StatusClienteCartao::Ativo,
        ]));
    }

    public function atualizarApelido(Cliente $cliente, ClienteCartao $cartao, string $apelido): bool
    {
        if ($cartao->cliente_id !== $cliente->id) {
            return false;
        }

        $cartao->apelido = $apelido;

        return $cartao->save();
    }

    /** Desativa localmente; a remoção no cofre do gateway depende da integração escolhida. */
    public function excluir(Cliente $cliente, ClienteCartao $cartao): bool
    {
        if ($cartao->cliente_id !== $cliente->id) {
            return false;
        }

        $cartao->status = StatusClienteCartao::Inativo;

        return $cartao->save();
    }
}
