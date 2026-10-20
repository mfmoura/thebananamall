<?php

namespace App\Services;

use App\Models\Carrinho;
use App\Models\Cliente;
use App\Models\Produto;
use App\Webservices\HubCorreiosClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FreteService
{
    public function __construct(private readonly CarrinhoService $carrinhos, private readonly HubCorreiosClient $hub) {}

    public function cotar(Cliente $cliente, string $cep): array
    {
        $carrinho = $this->carrinhos->consultar($cliente);
        abort_unless($carrinho && $carrinho->itens, 422, 'O carrinho está vazio ou expirou.');
        $produtos = Produto::whereIn('id', array_map(fn ($item) => $item->codigo, $carrinho->itens))->where('ativo', true)->get()->keyBy('id');
        [$pacote, $assinatura] = $this->preparar($carrinho, $produtos);
        $opcoes = [];
        foreach (config('frete.servicos') as $codigo => $nome) {
            $codigo = (string) $codigo;
            $chave = 'frete:hub:'.hash('sha256', json_encode([$cep, $pacote, $codigo, config('frete.cep_origem'), hash('sha256', (string) config('frete.token'))]));
            $resultado = Cache::remember($chave, 120, fn () => $this->hub->cotar($cep, $pacote, $codigo));
            if (! is_array($resultado)) continue;
            if (! is_string($resultado['valor_total'] ?? null) || ! is_string($resultado['prazo_de_entrega'] ?? null)) continue;
            $valor = preg_replace('/^R\$\s*/u', '', trim((string) ($resultado['valor_total'] ?? '')));
            $prazo = trim((string) ($resultado['prazo_de_entrega'] ?? ''));
            if (! preg_match('/^(?:\d{1,3}(?:\.\d{3})*|\d+),\d{2}$/', $valor) || $prazo === '' || mb_strlen($prazo) > 180) continue;
            $centavos = (int) str_replace(['.', ','], '', $valor);
            if ($centavos <= 0 || $centavos > 999999999) continue;
            $opcoes[] = ['codigo' => $codigo, 'nome' => $nome, 'valor' => number_format($centavos / 100, 2, '.', ''), 'prazo' => $prazo];
        }
        abort_unless($opcoes, 503, 'Não foi possível obter frete para este endereço. Tente novamente mais tarde.');
        $id = (string) Str::uuid();
        $expira = now()->addSeconds(config('frete.validade_segundos', 300));
        Cache::put($this->chave($cliente, $id), [
            'cep' => $cep, 'assinatura' => $assinatura, 'opcoes' => $opcoes, 'pacote' => $pacote,
            'expira_em' => $expira->timestamp, 'origem' => config('frete.cep_origem'),
            'modalidade_fiscal' => (int) config('frete.modalidade_fiscal', 0),
        ], $expira);

        return ['cotacao_id' => $id, 'cep' => $cep, 'expira_em' => $expira->timestamp, 'pacote' => $pacote, 'opcoes' => $opcoes];
    }

    public function confirmar(Cliente $cliente, string $id, string $servico, string $cep, Carrinho $carrinho, Collection $produtos): array
    {
        $cotacao = Cache::get($this->chave($cliente, $id));
        [, $assinatura] = $this->preparar($carrinho, $produtos);
        if (! is_array($cotacao) || $cotacao['expira_em'] <= now()->timestamp || $cotacao['cep'] !== $cep
            || ! hash_equals($cotacao['assinatura'], $assinatura) || $cotacao['origem'] !== config('frete.cep_origem')) {
            throw ValidationException::withMessages(['frete_cotacao_id' => 'O carrinho, o endereço ou a cotação mudou. Calcule o frete novamente.']);
        }
        $opcao = collect($cotacao['opcoes'])->firstWhere('codigo', $servico);
        if (! $opcao) {
            throw ValidationException::withMessages(['frete_servico' => 'Selecione um serviço desta cotação.']);
        }

        return [...$opcao, 'cep_destino' => $cep, 'cep_origem' => $cotacao['origem'], 'pacote' => $cotacao['pacote'],
            'modalidade_fiscal' => $cotacao['modalidade_fiscal'], 'provedor' => 'hub_desenvolvedor'];
    }

    private function chave(Cliente $cliente, string $id): string
    {
        return 'frete:cotacao:'.$cliente->id.':'.$id;
    }

    private function preparar(Carrinho $carrinho, Collection $produtos): array
    {
        $pacote = ['peso_gramas' => 0, 'altura_cm' => 0, 'largura_cm' => 15, 'comprimento_cm' => 20, 'estimado' => false];
        $linhas = [];
        foreach ($carrinho->itens as $item) {
            $produto = $produtos->get($item->codigo);
            if (! $produto || ! $produto->peso_gramas || ! $produto->altura_cm || ! $produto->largura_cm || ! $produto->comprimento_cm) {
                throw ValidationException::withMessages(['frete' => 'Um produto está sem peso ou medidas de envio. Entre em contato com a loja.']);
            }
            $quantidade = (int) ceil($item->quantidade());
            $pacote['peso_gramas'] += (int) $produto->peso_gramas * $quantidade;
            $pacote['altura_cm'] += (float) $produto->altura_cm * $quantidade;
            $pacote['largura_cm'] = max($pacote['largura_cm'], (float) $produto->largura_cm);
            $pacote['comprimento_cm'] = max($pacote['comprimento_cm'], (float) $produto->comprimento_cm);
            $pacote['estimado'] = $pacote['estimado'] || $produto->embalagem_estimada;
            $linhas[$item->chave()] = [$item->quantidade(), $produto->preco, $produto->peso_gramas,
                $produto->altura_cm, $produto->largura_cm, $produto->comprimento_cm, $produto->embalagem_estimada];
        }
        ksort($linhas);
        $pacote['altura_cm'] = max(2, (int) ceil($pacote['altura_cm']));
        $pacote['largura_cm'] = (int) ceil($pacote['largura_cm']);
        $pacote['comprimento_cm'] = (int) ceil($pacote['comprimento_cm']);
        if ($pacote['peso_gramas'] > 30000 || max($pacote['altura_cm'], $pacote['largura_cm'], $pacote['comprimento_cm']) > 100
            || $pacote['altura_cm'] + $pacote['largura_cm'] + $pacote['comprimento_cm'] > 200) {
            throw ValidationException::withMessages(['frete' => 'Este carrinho precisa de envio em mais de um volume. Entre em contato com a loja.']);
        }

        return [$pacote, hash('sha256', json_encode($linhas, JSON_THROW_ON_ERROR))];
    }
}
