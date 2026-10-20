# Frete integrado ao Hub do Desenvolvedor

O checkout usa `POST /api/pedidos/frete`, autenticado como Cliente com escopo `orders:write`, para cotar PAC e SEDEX do carrinho atual. O navegador usa o proxy permitido `/bff/api/pedidos/frete`, cookie HttpOnly e CSRF. A API recebe somente `cep` (oito dígitos); preço, pacote, origem e credencial são definidos pelo servidor.

Configuração em `config/frete.php`: token `HUB_DESENVOLVEDOR_TOKEN` no `.env`, origem `FRETE_CEP_ORIGEM` (padrão local autorizado `03044000`), validade de cinco minutos e códigos PAC `04510`/SEDEX `04014`. O Hub recebe peso convertido de gramas para quilogramas, conforme comportamento observado no [diagnóstico](HUB-FRETE-TESTE.md). A credencial não é retornada ao React, incluída nos snapshots ou propagada em mensagens de erro.

## Produtos e pacote

No dashboard, a edição/cadastro de produto inclui `peso_gramas`, `altura_cm`, `largura_cm`, `comprimento_cm` e `embalagem_estimada`. O peso inclui a embalagem. Informe os quatro campos juntos, ou deixe todos sem preenchimento. Sem esses dados, a compra exige atendimento da loja em vez de assumir peso zero. A API administrativa de produtos aceita os mesmos campos com as permissões existentes.

A regra inicial considera um único pacote: soma dos pesos e das alturas unitárias multiplicados pela quantidade, maior largura e maior comprimento. Quantidades fracionárias são arredondadas para cima para dimensionar o volume. O pacote respeita mínimos operacionais de 2 × 15 × 20 cm e arredonda as dimensões para cima. Carrinhos acima de 30 kg, dimensão individual de 100 cm ou soma das dimensões de 200 cm exigem atendimento para múltiplos volumes. Não existe algoritmo de otimização/rotação ou contratação de múltiplos volumes nesta entrega. A regra de empilhamento deve ser conferida com a embalagem usada na expedição.

O seeder `ProdutoEmbalagemTesteSeeder`, exclusivo de desenvolvimento/testes, preenche somente códigos conhecidos dos 15 produtos de demonstração e não sobrescreve produtos que já têm peso. Também participa do seeder geral nos ambientes permitidos. Estimativas cadastradas:

| Produto | Peso com embalagem | Altura × largura × comprimento |
|---|---:|---|
| Bandeira | 250 g | 3 × 20 × 25 cm |
| Botton | 30 g | 2 × 8 × 8 cm |
| Camiseta | 300 g | 4 × 25 × 30 cm |
| Regata/cropped | 200 g | 3 × 20 × 25 cm |
| Moletom | 800 g | 10 × 28 × 35 cm |

Todos ficam com `embalagem_estimada=true`; a administração pode substituir os valores por medições e desmarcar a opção. O checkout informa quando o pacote deriva de estimativas.

## Cotação e confirmação

A resposta contém `cotacao_id`, `expira_em` (Unix timestamp), `cep`, `pacote` e `opcoes`, com `codigo`, `nome`, `valor` decimal em reais e `prazo` retornado pelo provedor. O cliente escolhe o serviço e confirma o pedido. O texto de prazo inclui o dia da postagem; a aplicação não o converte em data prometida.

O servidor mantém a cotação temporária no cache por cinco minutos, vinculada ao Cliente, destino e composição do carrinho (quantidades, preços e medidas). O resultado bruto de uma mesma consulta ao Hub é reutilizado por até dois minutos. Se somente uma modalidade estiver disponível, apenas ela é oferecida. Se nenhuma estiver disponível ou o provedor falhar, retorna 503, sem liberar compra com frete gratuito. Não há fallback silencioso de preço.

`POST /api/pedidos` exige `frete_cotacao_id` e `frete_servico`, além dos campos anteriores. `custo_frete` e `frete` são proibidos na entrada. Na transação do pedido, com os produtos bloqueados, a API verifica validade, titularidade, CEP, itens, preços e medidas, sem fazer chamadas externas sob o bloqueio de estoque. Alteração de origem também invalida a seleção. Divergências exigem nova cotação e retornam 422.

O pedido persiste `custo_frete` e o snapshot `frete`: provedor, serviço, valor, prazo, CEPs, pacote e modalidade fiscal. `subtotal` soma os itens e `total` inclui o frete. Pedido, pagamento e dashboard mostram essa separação. Pedidos anteriores permanecem com frete zero. Repetir uma compra com a mesma chave de idempotência retorna o pedido já criado, mesmo após expiração da cotação, sem cobrar novamente ou recalcular seu frete.

O payload de pagamento Mercado Pago e a conciliação usam o total persistido do pedido. O processamento fiscal preserva `total_frete`, informa o pagamento com frete e rateia o valor em centavos nos campos `items[].freightAmount` enviados à NFE.io; `modalidade_fiscal=0` corresponde a `transport.freightModality=ByIssuer` e deve refletir a contratação da loja. Retentativas consultam o mesmo documento e snapshot; resultado incerto da criação exige reconciliação antes de outro envio. Referência: [integração NFE.io](FISCAL-NFE-IO.md).

## Limites da integração

Esta integração faz cotação e cobra o valor selecionado. O Hub não foi integrado para comprar postagem, emitir etiquetas ou atualizar rastreamento. O simulacro de despacho continua identificado como demonstração. O cadastro de produtos e o cálculo do pacote precisam corresponder às embalagens físicas para que a cotação reflita o envio real.

Após aplicar a migration, execute o seeder de estimativas somente no ambiente local. Atualize o cache de configuração e reinicie os workers conforme o procedimento de operação do ambiente ao publicar as mudanças.
