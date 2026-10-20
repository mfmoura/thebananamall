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
    public function __construct(private readonly GradeProdutoService $grades) {}

    public function contarEstoque(Produto $produto, ?string $tamanho = null): float
    {
        $entradas = $produto->movimentacoes()->withTrashed()
            ->when($tamanho !== null, fn ($q) => $q->where('tamanho', $tamanho))
            ->where('direcao', DirecaoMovimentacao::Entrada->value)->sum('quantidade');
        $saidas = $produto->movimentacoes()->withTrashed()
            ->when($tamanho !== null, fn ($q) => $q->where('tamanho', $tamanho))
            ->where('direcao', DirecaoMovimentacao::Saida->value)->sum('quantidade');

        return round((float) $entradas - (float) $saidas, 3);
    }

    public function contarReservado(int $produtoId, ?string $ignorarDono = null, ?string $tamanho = null): float
    {
        $chave = $this->chaveProduto($produtoId);
        $reservas = Redis::hgetall($chave);
        $agora = now()->timestamp;
        $total = 0.0;

        foreach ($reservas as $dono => $json) {
            $reserva = is_string($json) ? json_decode($json, true) : null;

            if (! is_array($reserva) || $this->expiracaoEfetiva($reserva) <= $agora
                || ! Redis::exists('carrinho:'.$dono)) {
                Redis::hdel($chave, $dono);
                continue;
            }

            if ($dono !== $ignorarDono) {
                $total += $this->quantidadeReservada($reserva, $tamanho);
            }
        }

        return round($total, 3);
    }

    public function contarDisponivel(Produto $produto, ?string $tamanho = null): float
    {
        return round($this->contarEstoque($produto, $tamanho) - $this->contarReservado($produto->id, null, $tamanho), 3);
    }

    /** Consultar sob o bloqueio global de estoque. */
    public function resumoPorTamanho(Produto $produto): array
    {
        if (empty($produto->grade)) return [];

        $saldos = $produto->movimentacoes()->withTrashed()->select('tamanho')
            ->selectRaw("SUM(CASE WHEN direcao = 'entrada' THEN quantidade ELSE -quantidade END) AS saldo")
            ->groupBy('tamanho')->get()->keyBy('tamanho');
        $reservadoPorTamanho = [];
        $chave = $this->chaveProduto($produto->id);
        foreach (Redis::hgetall($chave) as $dono => $json) {
            $reserva = is_string($json) ? json_decode($json, true) : null;
            if (! is_array($reserva) || $this->expiracaoEfetiva($reserva) <= now()->timestamp
                || ! Redis::exists('carrinho:'.$dono)) {
                Redis::hdel($chave, $dono);
                continue;
            }
            foreach ($reserva['tamanhos'] ?? [] as $tamanho => $quantidade) {
                $reservadoPorTamanho[$tamanho] = ($reservadoPorTamanho[$tamanho] ?? 0) + (float) $quantidade;
            }
        }

        return array_map(static function (string $tamanho) use ($saldos, $reservadoPorTamanho): array {
            $contado = round((float) ($saldos->get($tamanho)?->saldo ?? 0), 3);
            $reservado = round($reservadoPorTamanho[$tamanho] ?? 0, 3);
            return ['tamanho' => $tamanho, 'estoque_contado' => $contado,
                'estoque_reservado' => $reservado, 'estoque' => round($contado - $reservado, 3)];
        }, $produto->grade);
    }

    /** @param array<int, string> $ignorarDonos */
    public function validarCarrinho(string $dono, Carrinho $carrinho, array $ignorarDonos = []): void
    {
        $chaves = array_map(fn ($item) => $item->chave(), $carrinho->itens);
        if (count($chaves) !== count(array_unique($chaves))) {
            throw ValidationException::withMessages(['itens' => 'Não repita o mesmo produto e tamanho.']);
        }
        foreach ($carrinho->itens as $item) {
            $produto = Produto::query()->whereKey($item->codigo)->where('ativo', true)->first();
            if ($produto !== null) {
                $this->grades->validarTamanho($produto, $item->tamanho, $item->quantidade());
            }
            $estoque = $produto === null ? 0.0 : $this->contarEstoque($produto, $item->tamanho);
            $reservadoPorOutros = $this->contarReservadoIgnorando(
                $item->codigo,
                array_merge([$dono], $ignorarDonos),
                $item->tamanho,
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
    private function contarReservadoIgnorando(int $produtoId, array $donos, ?string $tamanho = null): float
    {
        $reservado = $this->contarReservado($produtoId, null, $tamanho);
        $chave = $this->chaveProduto($produtoId);

        foreach (array_unique($donos) as $dono) {
            $json = Redis::hget($chave, $dono);
            $reserva = is_string($json) ? json_decode($json, true) : null;
            if (is_array($reserva) && $this->expiracaoEfetiva($reserva) > now()->timestamp) {
                $reservado -= $this->quantidadeReservada($reserva, $tamanho);
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

            $itens = array_filter($novo->itens, fn ($item) => $item->codigo === $codigo);
            if ($itens !== []) {
                $tamanhos = [];
                $total = 0;
                foreach ($itens as $item) {
                    $total += $item->quantidade();
                    if ($item->tamanho !== null) $tamanhos[$item->tamanho] = $item->quantidade();
                }
                Redis::hset($chave, $identificador, json_encode([
                    'versao' => 2, 'quantidade' => $total, 'tamanhos' => $tamanhos, 'expira_em' => $expiraEm,
                ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
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

    /** SCAN paginado; retorna somente chaves do índice desta aplicação. */
    public function produtosIndexados(): \Generator
    {
        foreach ($this->chavesIndexadas('estoque:reservas:produto:*') as $chave) {
            if (preg_match('/^estoque:reservas:produto:(\d+)$/', $chave, $match)) {
                yield (int) $match[1];
            }
        }
    }

    public function carrinhosIndexados(): \Generator
    {
        foreach ($this->chavesIndexadas('carrinho:*') as $chave) {
            if (preg_match('/^carrinho:(cliente|visitante):\d+$/', $chave)) {
                yield $chave;
            }
        }
    }

    /** Adequa o prazo antigo sem tratar uma consulta como nova atividade. */
    public function adequarCarrinhoLegado(string $chave): void
    {
        $json = Redis::get($chave);
        $dados = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($dados) || isset($dados['politica_versao'])) {
            return;
        }
        $restanteAntigo = (int) Redis::ttl($chave);
        if ($restanteAntigo < 0) {
            // Sem TTL não há evidência de atividade recente: preserva itens, sem reservar.
            $idade = (int) config('carrinho.reserva_ttl');
        } else {
            $idade = max(0, (int) config('carrinho.ttl') - $restanteAntigo);
        }
        $ttl = (int) config(str_starts_with($chave, 'carrinho:cliente:') ? 'carrinho.cliente_ttl' : 'carrinho.visitante_ttl');
        if ($idade >= $ttl) {
            Redis::del($chave);

            return;
        }
        $dados['politica_versao'] = 2;
        $dados['reserva_expira_em'] = now()->timestamp - $idade + min($ttl, (int) config('carrinho.reserva_ttl'));
        Redis::setex($chave, $ttl - $idade, json_encode($dados, JSON_THROW_ON_ERROR));
    }

    private function chavesIndexadas(string $padrao): \Generator
    {
        $cursor = null;
        $prefixo = (string) config('database.redis.options.prefix', '');
        do {
            $pagina = Redis::connection()->scan($cursor, ['match' => $prefixo.$padrao, 'count' => 100]);
            if ($pagina === false) {
                break;
            }
            [$cursor, $chaves] = $pagina;
            foreach ($chaves as $chave) {
                $logica = substr($chave, strlen($prefixo));
                yield $logica;
            }
        } while ((int) $cursor !== 0);
    }

    /** Executar sob o mesmo bloqueio global utilizado pelas mutações e checkout. */
    public function limparProduto(int $produtoId): int
    {
        $chave = $this->chaveProduto($produtoId);
        $removidas = 0;
        foreach (Redis::hgetall($chave) as $dono => $json) {
            $reserva = is_string($json) ? json_decode($json, true) : null;
            $chaveCarrinho = 'carrinho:'.$dono;
            $jsonCarrinho = Redis::get($chaveCarrinho);
            $dados = is_string($jsonCarrinho) ? json_decode($jsonCarrinho, true) : null;
            $temItem = is_array($dados) && collect($dados['itens'] ?? [])->contains(
                fn ($item) => (int) ($item['codigo'] ?? 0) === $produtoId,
            );
            if (! is_array($reserva) || ! $temItem || $this->expiracaoEfetiva($reserva) <= now()->timestamp) {
                $removidas += Redis::hdel($chave, $dono);
            } elseif (! isset($reserva['versao'])) {
                $reserva['expira_em'] = $this->expiracaoEfetiva($reserva);
                $reserva['versao'] = 2;
                Redis::hset($chave, $dono, json_encode($reserva, JSON_THROW_ON_ERROR));
            }
        }

        return $removidas;
    }

    private function expiracaoEfetiva(array $reserva): int
    {
        $prazo = (int) ($reserva['expira_em'] ?? 0);

        return isset($reserva['versao']) ? $prazo
            : min($prazo, $prazo - (int) config('carrinho.ttl') - 1 + (int) config('carrinho.reserva_ttl'));
    }

    private function quantidadeReservada(array $reserva, ?string $tamanho): float
    {
        return (float) ($tamanho === null ? ($reserva['quantidade'] ?? 0) : ($reserva['tamanhos'][$tamanho] ?? 0));
    }

    private function chaveProduto(int $produtoId): string
    {
        return "estoque:reservas:produto:{$produtoId}";
    }
}
