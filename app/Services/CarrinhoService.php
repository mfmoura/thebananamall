<?php

namespace App\Services;

use App\Models\Carrinho;
use App\Models\Cliente;
use App\Models\Produto;
use App\Models\Visitante;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CarrinhoService
{
    public function __construct(
        private readonly VisitanteService $visitanteService,
        private readonly ReservaEstoqueService $reservaEstoqueService,
    ) {}

    public function consultar(Cliente|Visitante $dono): ?Carrinho
    {
        $json = Redis::get($this->chave($dono));

        if ($json === null || $json === false) {
            return null;
        }

        return $this->atualizarPrecosAtuais(Carrinho::deArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR)));
    }

    public function criar(Cliente|Visitante $dono): Carrinho
    {
        return $this->comLock($dono, fn (): Carrinho =>
            $this->consultar($dono) ?? $this->salvar($dono, $this->novo($dono))
        );
    }

    /**
     * @param array<int, array{codigo: int|string, quantidade: int|float|string}> $itens
     */
    public function atualizar(Cliente|Visitante $dono, array $itens): ?Carrinho
    {
        return $this->comLock($dono, function () use ($dono, $itens): ?Carrinho {
            $carrinho = $this->consultar($dono);

            if ($carrinho === null) {
                return null;
            }

            return $this->salvar($dono, $carrinho->substituirProdutos($itens));
        });
    }

    public function adicionarProduto(Cliente|Visitante $dono, int $codigo, float $quantidade = 1): Carrinho
    {
        $preco = Produto::query()->whereKey($codigo)->first()?->preco;

        return $this->comLock($dono, function () use ($dono, $codigo, $quantidade, $preco): Carrinho {
            $carrinho = $this->consultar($dono) ?? $this->novo($dono);

            return $this->salvar($dono, $carrinho->adicionarProduto($codigo, $quantidade, $preco));
        });
    }

    public function atualizarItem(Cliente|Visitante $dono, int $codigo, float $quantidade): ?Carrinho
    {
        return $this->comLock($dono, function () use ($dono, $codigo, $quantidade): ?Carrinho {
            $carrinho = $this->consultar($dono);
            $carrinhoAtualizado = $carrinho?->alterarQuantidade($codigo, $quantidade);

            if ($carrinhoAtualizado === null) {
                return null;
            }

            return $this->salvar($dono, $carrinhoAtualizado);
        });
    }

    public function excluirItem(Cliente|Visitante $dono, int $codigo): bool
    {
        return $this->comLock($dono, function () use ($dono, $codigo): bool {
            $carrinho = $this->consultar($dono);
            $carrinhoAtualizado = $carrinho?->excluirItem($codigo);

            if ($carrinhoAtualizado === null) {
                return false;
            }

            $this->salvar($dono, $carrinhoAtualizado);

            return true;
        });
    }

    public function excluir(Cliente|Visitante $dono): bool
    {
        return $this->comLock($dono, fn (): bool => $this->removerSobBloqueio($dono));
    }

    /** Executa operaÃ§Ãµes de estoque e carrinho sob o lock global de reservas. */
    public function comBloqueioDeEstoque(Closure $operacao): mixed
    {
        try {
            return Cache::lock('lock:estoque:reservas', (int) config('carrinho.lock_ttl', 120))
                ->block((int) config('carrinho.lock_wait', 10), $operacao);
        } catch (LockTimeoutException $exception) {
            throw new ConflictHttpException('O estoque estÃ¡ sendo atualizado. Tente novamente.', $exception);
        }
    }

    /** Só deve ser chamado dentro de comBloqueioDeEstoque(). */
    public function removerSobBloqueio(Cliente|Visitante $dono): bool
    {
        $json = Redis::get($this->chave($dono));
        $carrinho = is_string($json)
            ? Carrinho::deArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR))
            : null;
        $removido = Redis::del($this->chave($dono)) > 0;
        $this->reservaEstoqueService->remover($dono, $carrinho);

        return $removido;
    }

    public function mesclar(Cliente $cliente, Visitante $visitante): Carrinho
    {
        return $this->comLocks([$cliente, $visitante], function () use ($cliente, $visitante): Carrinho {
            $carrinhoVisitante = $this->consultar($visitante);
            $carrinhoCliente = $this->consultar($cliente) ?? Carrinho::paraCliente($cliente->id);

            if ($carrinhoVisitante === null) {
                $carrinhoSalvo = $this->salvar($cliente, $carrinhoCliente);
                $this->visitanteService->invalidar($visitante);

                return $carrinhoSalvo;
            }

            $carrinhoMesclado = $carrinhoCliente->mesclar($carrinhoVisitante);
            $carrinhoSalvo = $this->salvar($cliente, $carrinhoMesclado, [
                $this->reservaEstoqueService->identificadorDono($visitante),
            ]);
            $this->reservaEstoqueService->remover($visitante, $carrinhoVisitante);
            Redis::del($this->chave($visitante));
            $this->visitanteService->invalidar($visitante);

            return $carrinhoSalvo;
        });
    }

    /** @template TResult @param Closure(): TResult $operacao @return TResult */
    private function comLock(Cliente|Visitante $dono, Closure $operacao): mixed
    {
        return $this->comLocks([$dono], $operacao);
    }

    /** @template TResult @param array<int, Cliente|Visitante> $donos @param Closure(): TResult $operacao @return TResult */
    private function comLocks(array $donos, Closure $operacao): mixed
    {
        $chaves = array_values(array_unique(array_map(
            fn (Cliente|Visitante $dono): string => 'lock:' . $this->chave($dono),
            $donos,
        )));
        sort($chaves, SORT_STRING);

        $adquirir = function (int $indice) use (&$adquirir, $chaves, $operacao): mixed {
            if (! isset($chaves[$indice])) {
                return $operacao();
            }

            return Cache::lock($chaves[$indice], (int) config('carrinho.lock_ttl', 120))
                ->block((int) config('carrinho.lock_wait', 10), fn (): mixed => $adquirir($indice + 1));
        };

        return $this->comBloqueioDeEstoque(fn (): mixed => $adquirir(0));
    }

    /** @param array<int, string> $ignorarReservas */
    private function salvar(Cliente|Visitante $dono, Carrinho $carrinho, array $ignorarReservas = []): Carrinho
    {
        $chave = $this->chave($dono);
        $jsonAnterior = Redis::get($chave);
        $carrinhoAnterior = is_string($jsonAnterior)
            ? Carrinho::deArray(json_decode($jsonAnterior, true, 512, JSON_THROW_ON_ERROR))
            : null;
        $assinatura = static function (Carrinho $valor): array {
            $itens = [];
            foreach ($valor->itens as $item) {
                $itens[$item->codigo] = $item->quantidade();
            }
            ksort($itens);

            return $itens;
        };
        if ($carrinhoAnterior !== null && $assinatura($carrinhoAnterior) === $assinatura($carrinho)) {
            return $this->atualizarPrecosAtuais($carrinhoAnterior);
        }
        $this->reservaEstoqueService->validarCarrinho(
            $this->reservaEstoqueService->identificadorDono($dono),
            $carrinho,
            $ignorarReservas,
        );
        $ttl = (int) config($dono instanceof Cliente ? 'carrinho.cliente_ttl' : 'carrinho.visitante_ttl');
        $carrinho->reserva_expira_em = now()->addSeconds(min($ttl, (int) config('carrinho.reserva_ttl')))->timestamp;
        Redis::setex(
            $chave,
            $ttl,
            json_encode($carrinho->toArray() + ['politica_versao' => 2], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
        );
        $this->reservaEstoqueService->sincronizar(
            $dono,
            $carrinhoAnterior,
            $carrinho,
            $carrinho->reserva_expira_em,
        );

        if ($dono instanceof Visitante) {
            $this->visitanteService->renovar($dono);
        }

        return $this->atualizarPrecosAtuais($carrinho);
    }

    public function sanitizarReservas(): int
    {
        $total = 0;
        foreach ($this->reservaEstoqueService->carrinhosIndexados() as $chave) {
            $this->comBloqueioDeEstoque(fn () => $this->reservaEstoqueService->adequarCarrinhoLegado($chave));
        }
        foreach ($this->reservaEstoqueService->produtosIndexados() as $produtoId) {
            $total += $this->comBloqueioDeEstoque(fn () => $this->reservaEstoqueService->limparProduto($produtoId));
        }

        return $total;
    }

    /** Checkout já mantém o bloqueio global; reservas vencidas não garantem estoque. */
    public function validarParaCheckout(Cliente $cliente, Carrinho $carrinho): void
    {
        $this->reservaEstoqueService->validarCarrinho(
            $this->reservaEstoqueService->identificadorDono($cliente), $carrinho,
        );
    }

    private function chave(Cliente|Visitante $dono): string
    {
        return $dono instanceof Cliente
            ? "carrinho:cliente:{$dono->id}"
            : "carrinho:visitante:{$dono->id}";
    }

    private function novo(Cliente|Visitante $dono): Carrinho
    {
        return $dono instanceof Cliente
            ? Carrinho::paraCliente($dono->id)
            : Carrinho::paraVisitante($dono->id);
    }

    private function atualizarPrecosAtuais(Carrinho $carrinho): Carrinho
    {
        $codigos = array_map(static fn ($item): int => $item->codigo, $carrinho->itens);
        $precos = Produto::query()->whereIn('id', $codigos)->with('precoAtual')->get()
            ->mapWithKeys(static fn (Produto $produto): array => [
                $produto->id => $produto->precoAtual?->preco,
            ]);

        foreach ($carrinho->itens as $item) {
            $item->definirPrecoAtual($precos->get($item->codigo));
        }

        return $carrinho;
    }
}
