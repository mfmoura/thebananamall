<?php

namespace Tests\Unit;

use App\Models\CarrinhoItem;
use PHPUnit\Framework\TestCase;

class CarrinhoItemTest extends TestCase
{
    public function test_detecta_aumento_e_reducao_sem_substituir_preco_original(): void
    {
        $item = new CarrinhoItem(1, 2, '2026-10-20', '20.00');

        foreach (['25.00', '15.00'] as $preco) {
            $item->definirPrecoAtual($preco);
            $dados = $item->jsonSerialize();
            $this->assertTrue($dados['modificado']);
            $this->assertSame('20.00', $dados['preco']);
            $this->assertSame($preco, $dados['preco_atual']);
        }
    }

    public function test_nao_alerta_para_precos_equivalentes_ou_ausentes(): void
    {
        $item = new CarrinhoItem(1, 1, '2026-10-20', '20.00');
        $this->assertFalse($item->alertarModificacao());
        $item->definirPrecoAtual('20');
        $this->assertFalse($item->alertarModificacao());
        $semPreco = new CarrinhoItem(1, 1, '2026-10-20');
        $semPreco->definirPrecoAtual('20.00');
        $this->assertFalse($semPreco->alertarModificacao());
    }
}
