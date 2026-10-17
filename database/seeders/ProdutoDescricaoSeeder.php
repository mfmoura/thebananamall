<?php

namespace Database\Seeders;

use App\Models\Produto;
use Illuminate\Database\Seeder;

class ProdutoDescricaoSeeder extends Seeder
{
    public function run(): void
    {
        Produto::query()->where(function ($query): void {
            $query->whereNull('descricao')->orWhere('descricao', '');
        })->chunkById(100, function ($produtos): void {
            foreach ($produtos as $produto) {
                $produto->update(['descricao' => $this->descricao($produto)]);
            }
        });
    }

    private function descricao(Produto $produto): string
    {
        $textos = [
            'LCB-BANDEIRA-001' => 'Bandeira da Liga Comunista Brasileira para acompanhar atos, encontros e atividades de organização. Um símbolo da nossa militância e do compromisso com a construção coletiva.',
            'LCB-BOTTON-001' => 'Leve a mensagem “A luta continua” para o cotidiano. Este botton da LCB pode compor a apresentação de mochilas, bolsas e roupas, expressando o compromisso com a luta dos trabalhadores.',
            'LCB-CAMISETA-001' => 'Camiseta da LCB com a mensagem “Trabalhadores de todo o mundo, uni-vos”. Uma peça para expressar a solidariedade de classe em encontros, atividades da militância e no dia a dia.',
            'LCB-DEMO-CAMISETA-VERMELHA' => 'O vermelho da nossa bandeira em uma camiseta com a identidade da LCB. Uma opção para acompanhar atividades políticas, encontros e momentos de convivência entre camaradas.',
            'LCB-DEMO-CAMISETA-PRETA' => 'Camiseta preta com a identidade da Liga Comunista Brasileira em destaque. Uma composição para levar a presença da militância aos espaços do cotidiano.',
            'LCB-DEMO-CAMISETA-BRANCA' => 'Camiseta branca com a identidade da LCB. O contraste da composição destaca o símbolo da organização para quem deseja vestir sua ligação com a luta coletiva.',
            'LCB-DEMO-REGATA' => 'Regata LCB com o tema “Organização e luta”. Uma peça de vestuário para expressar o compromisso com a ação coletiva e acompanhar os diferentes momentos da militância.',
            'LCB-DEMO-MOLETOM' => 'Moletom da LCB inspirado na solidariedade entre trabalhadores. A identidade da organização acompanha uma peça para compor o vestuário em encontros e atividades do coletivo.',
            'LCB-DEMO-CROPPED' => 'Cropped LCB com o tema “Poder popular”. Uma forma de incorporar a identidade da organização ao vestuário e levar a mensagem da construção coletiva para o dia a dia.',
            'LCB-DEMO-BOTTON-ESTRELA' => 'Botton da LCB com estrela vermelha, símbolo presente na história das lutas comunistas. Uma peça para acompanhar roupas, bolsas e mochilas nos espaços da militância.',
            'LCB-DEMO-BOTTON-UNIDADE' => 'Botton LCB com o tema “Unidade”. Uma lembrança cotidiana da importância da organização e da ação conjunta dos trabalhadores.',
            'LCB-DEMO-BOTTON-SOLIDARIEDADE' => 'Botton LCB dedicado à solidariedade de classe. Uma peça para expressar o compromisso com quem luta e com a construção de vínculos entre trabalhadores.',
            'LCB-DEMO-BOTTON-TRABALHADORES' => 'Botton da LCB em homenagem aos trabalhadores e às suas lutas. Leve a identidade da organização para suas roupas e acessórios.',
            'LCB-DEMO-BOTTON-PODER' => 'Botton LCB com o tema “Poder popular”. Uma peça que remete à participação, à organização de base e à construção coletiva de uma nova sociedade.',
            'LCB-DEMO-BOTTON-LUTA' => 'Botton LCB com a mensagem “A luta continua”. Um símbolo da persistência e do compromisso coletivo que acompanham a militância em cada nova tarefa.',
        ];

        return $textos[$produto->codigo] ?? "{$produto->nome}. Artigo da Liga Comunista Brasileira para acompanhar nossas atividades e expressar a identidade da militância no cotidiano.";
    }
}
