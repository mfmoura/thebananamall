# Homologação Mercado Pago

## Aceite do responsável — 20/10/2026

O responsável solicitou: “Homologa os pagamentos e commita e push”. Registrado o aceite funcional das entregas de pagamentos e do receptor corrigido, com base nos resultados abaixo. A assinatura local sem SDK autenticou seis capturas do simulador; replay pelo kernel HTTP Laravel retornou 200 duas vezes, persistiu um evento e concluiu a conciliação em fila. Pix e cartões de crédito possuem aprovações de homologação documentadas; boleto digital tem aceite anterior do responsável.

O aceite não equivale a certificação externa ou liberação para produção. Nova entrega automática pública após a correção, retentativas do emissor, aprovação do débito, compensação do boleto, matriz restante em navegador e adequação PCI continuam nos respectivos acompanhamentos. Não foram criadas cobranças adicionais para registrar este aceite.

Validação final anterior ao commit: `MercadoPagoAssinaturaTest|MercadoPagoTest|PixBoletoTest`, **54 testes aprovados, 254 assertions**. Execução isolada com SQLite em memória e provedor simulado, incluindo a compatibilidade do ID original do simulador, assinatura em minúsculas, rejeições, recuperação da fila, deduplicação e conciliação.

Atualização de assinatura em 20/10/2026: implementada validação HMAC local sem SDK. Cinco simulações capturadas da aplicação principal assinam a Order com ID original em maiúsculas e falham somente na variante em minúsculas. Adicionada compatibilidade com os dois manifests, sempre com HMAC válido. Seis capturas passaram; replay local de uma delas retornou HTTP 200 duas vezes, um evento persistido e conciliado na fila. Isso esclarece a falha dessas simulações; aceite de novas entregas automáticas públicas continua pendente. Ver [MERCADO-PAGO-SUPORTE-HMAC.md](MERCADO-PAGO-SUPORTE-HMAC.md). Nenhuma cobrança criada nesta correção.

Reteste adicional autorizado em 20/10/2026: Mastercard fictício oficial, titular `APRO`, R$ 50,00, pedido **23**, Order `ORDTST01M5DEC4MBR552X778HTQ9VBGN`, aprovado e conciliado pelo worker. Captura automática sem HTTP registrado no túnel e assinatura ainda `SignatureMismatch` no SDK com ID em minúsculas. O responsável esclareceu que a opção marcada era de PKCE/OAuth, sem comprovação de vínculo com o HMAC. Detalhes em [MERCADO-PAGO-SUPORTE-HMAC.md](MERCADO-PAGO-SUPORTE-HMAC.md). Nenhuma outra cobrança criada nesta tentativa.

Atualização do receptor em 20/10/2026: eventos autenticados agora são persistidos e conciliados na fila `pagamentos`; HTTP 200 não aguarda consulta ao provedor. Migration local aplicada e Horizon recarregado. Os 35 cenários distintos das suítes direcionadas passaram entre a execução inicial e as reexecuções descritas em [MERCADO-PAGO-SUPORTE-HMAC.md](MERCADO-PAGO-SUPORTE-HMAC.md). Um novo Pix autorizado, pedido **22**, Order `ORDTST01M5DB1GQZVNGVVZVSBF89B5DG`, foi aprovado e conciliado pelo worker; webhook automático ainda retornou **401**, sem evento autenticado persistido. HMAC obrigatório preservado e problema de assinatura continua aberto.

Reteste com cobrança nova após a correção de minúsculas, autorizado em 20/10/2026: Pix de R$ 50,00 com `APRO Teste`, pedido **21**, Order `ORDTST01M5D9VSWZFZ3S1AM6PXFQY6RQ`, aprovado e conciliado pelo worker. Webhook automático retornou **401**; duas capturas verificadas com ID em minúsculas e segredo atual continuam `SignatureMismatch`. Ambas contêm `data.id` e `x-request-id`. A regra de omissão de campos ausentes não explica esses casos. Evidências em [MERCADO-PAGO-SUPORTE-HMAC.md](MERCADO-PAGO-SUPORTE-HMAC.md).

Correção posterior em 20/10/2026: o receptor agora converte `data.id` para minúsculas somente para validar o manifest, preservando o ID na consulta. O SDK instalado não fazia essa normalização, apesar de documentá-la em comentário; os controles sintéticos anteriores também usavam maiúsculas. Cinco capturas do pedido 20 continuam com `SignatureMismatch` usando minúsculas. Ver detalhamento e ressalvas em [MERCADO-PAGO-SUPORTE-HMAC.md](MERCADO-PAGO-SUPORTE-HMAC.md). Nenhuma cobrança adicional criada nesta correção.

Retomada em 20/10/2026: evidências e texto para continuidade do chamado em [MERCADO-PAGO-SUPORTE-HMAC.md](MERCADO-PAGO-SUPORTE-HMAC.md). Histórico da vendedora bloqueado por propriedade OAuth no MCP; principal sem notificações retornadas. Novas capturas locais sem HTTP 200 automático comprovado. Horizon ativo; HMAC obrigatório e suspensão de novas cobranças preservados. WCS ainda não informado.

Reteste expressamente autorizado após nova redefinição da assinatura: um Pix de R$ 50,00 com `APRO Teste`, pedido 20, Order `ORDTST01M5D7RE4XB6TC5CR4SQCTYE0Q`, aprovado e conciliado pelo worker. Notificação automática retornou 401 e três capturas falharam com `SignatureMismatch` no SDK oficial usando o segredo atual, confirmado igual ao `.env`. Detalhes no acompanhamento acima; HMAC continua obrigatório e nenhuma outra cobrança foi criada neste reteste.

## Levantamento de 18/10/2026

Integração: Checkout Transparente, Orders API, Brasil, BRL, uma parcela.
Consulta remota autenticada: `GET https://api.mercadopago.com/v1/payment_methods`, usando a credencial do `.env`, sem registrar seu valor. Após o levantamento inicial, o responsável confirmou que a credencial veio da seção de testes e autorizou a continuidade dos cenários abaixo.

| Modalidade | IDs retornados como ativos | Limites retornados em BRL |
| --- | --- | --- |
| Crédito | `visa`, `master`, `elo`, `amex`, `mp_card` | 0,50 a 60.000,00 |
| Débito | `debelo` | 0,50 a 50.000,00 |
| Boleto | `bolbradesco` | 4,00 a 100.000,00 |
| Pré-pago | `visa`, `master`, `elo` | 0,50 a 50.000,00 |
| Saldo Mercado Pago | `account_money` | 0,00 a 10.000.000,00 |
| Pix | Não retornado | Não informado |

Esses metadados não comprovam aprovação de transações, habilitação em produção ou compatibilidade de cada ID com Orders. Pré-pago e saldo não são modalidades implementadas no checkout atual. As fixtures de débito Visa/Mastercard nos testes locais não representam os meios encontrados na consulta.

Em particular, não substituir `boleto` do payload Orders por `bolbradesco` apenas com base nessa lista: APIs diferentes podem usar identificadores diferentes. Confirmar o contrato e a emissão em Orders. Pix ausente também exige investigação; não é prova isolada de indisponibilidade. A documentação de Pix exige chave Pix cadastrada na conta.

## Execução remota com credencial de teste

Em 18/10/2026, foram criadas Orders diretamente no provedor, todas de R$ 50,00 e com chaves de idempotência distintas. Dados fictícios de teste foram enviados somente ao Mercado Pago. Tokens de cartão ficaram em memória e não foram registrados. Esta etapa não executou o checkout React, o worker Laravel ou a conciliação da loja.

| Cenário | Order ID | Último resultado consultado |
| --- | --- | --- |
| Pix, cenário oficial `APRO` | `ORDTST01M56VDW0W9181HWRA0G3WMGN6` | Inicialmente `processing/in_process`; na retomada, `processed/accredited`, sem instruções na consulta final |
| Boleto, CPF de teste e endereço completo | `ORDTST01M56VEFB4NT5891KSHPGAPE55` | Order `failed/failed`; pagamento `failed/processing_error`, sem linha digitável nem ticket |
| Crédito Visa | `ORDTST01M56VFC2VYZETWFAXESYM850N` | `processed/accredited`, total pago R$ 50,00 |
| Crédito Amex | `ORDTST01M56VFE7EZNRKVW3F92256KPM` | `processed/accredited`, total pago R$ 50,00 |
| Crédito Mastercard | `ORDTST01M56VH1K24JCE1A9YYKE4YT3R` | `processed/accredited`, total pago R$ 50,00 |
| Débito Elo (`debelo`) | Não criada | HTTP 400, `payment_method_not_allowed_for_collector` |

Pix foi criado às 06:33:17 UTC (03:33:17 de Brasília), boleto às 06:33:36 UTC (03:33:36 de Brasília). O boleto passou a falha às 06:34:44 UTC. As consultas posteriores não retornaram causa mais específica que `processing_error`. Não interpretar isso como problema comprovado no endereço ou na implementação da loja. O Pix foi consultado novamente na retomada e estava aprovado. O boleto original continua falho. Os novos cenários abaixo são independentes e não substituem tentativas de pedidos locais.

### Retomada de Pix e boleto — tarefa 2

Novos cenários executados em 18/10/2026, todos com credencial de teste confirmada, dados fictícios e valor de R$ 50,00:

| Cenário | Order ID | Evidência |
| --- | --- | --- |
| Pix oficial `APRO` | `ORDTST01M5850BYDCDB1F9TMFX6F9D9E` | Criação `action_required/waiting_transfer`, com `qr_code`, `qr_code_base64` e ticket. Consulta posterior `processed/accredited`, total pago R$ 50,00 |
| Reteste Pix oficial `APRO`, solicitado pelo responsável | `ORDTST01M585PTN17X32WJ06F7TR2C5S` | Criação `action_required/waiting_transfer`, com copia e cola, QR Code em base64 e ticket HTTPS. Consulta posterior `processed/accredited`, total e total pago R$ 50,00; aprovação simulada pelo provedor, sem pagamento real |
| Boleto de referência oficial, com localização | `ORDTST01M584Z1K17J4J1MRR8WVBZQ12` | `action_required/waiting_payment`, linha digitável de 47 caracteres, código de barras e ticket HTTPS |
| Boleto com CPF de teste válido e localização | `ORDTST01M5850DV98GXFHD8ZKDRJCZNZ` | `action_required/waiting_payment`, linha digitável e ticket |
| Boleto com o formato de payload da loja, sem localização extra | `ORDTST01M5851TW3KQSP9K938J93R16R` | `action_required/waiting_payment`, linha digitável e ticket HTTPS |

O último cenário reproduz os campos do envio original que falhou, com referência/chave novas para um teste independente. A emissão funcionou sem adicionar `config.location` nem mudar o contrato da loja. Não foi identificada uma correção de payload necessária. O erro original de processamento não se repetiu, mas sua causa não foi determinada; não foi mascarado nem convertido em sucesso.

As instruções foram verificadas por presença e formato, sem registrar QR Code, linha digitável ou URLs com identificadores do pagador neste documento. Para boleto, a homologação de emissão termina em espera de pagamento: não foi simulado um pagamento real para obter compensação. Testes remotos diretos comprovam emissão no provedor; navegação, worker e conciliação da loja continuam na tarefa de validação ponta a ponta.

Os cartões utilizados são os exemplos oficiais brasileiros para Mastercard, Visa, Amex e Elo débito, titular `APRO`, validade 11/2030. A tabela oficial consultada não forneceu cartão Elo crédito para o Brasil; não foi inventado um número para esse cenário. As aprovações remotas de crédito não comprovam habilitação comercial em produção.

O catálogo geral anunciou débito Elo como ativo, mas a criação em Orders foi recusada para o recebedor. Confirmar a habilitação específica com o Mercado Pago antes de considerar débito homologado. O identificador `boleto` foi aceito por Orders; a primeira emissão falhou, e os cenários de retomada acima confirmaram emissão com instruções.

### Disponibilidade de débito no Checkout Transparente

Conferência da documentação oficial em 18/10/2026: o guia de migração de Payments para Orders informa suporte no Brasil a **Elo e débito virtual Caixa**. A visão geral do Checkout Transparente também lista débito virtual Caixa. Portanto, existe suporte a débito no produto, com cobertura limitada; isso não comprova habilitação para este recebedor. O teste Elo desta conta retornou `payment_method_not_allowed_for_collector`. Suporte do produto e disponibilidade operacional da conta precisam ser tratados separadamente. O seletor de novas compras foi ajustado para ocultar débito até confirmar a habilitação da conta; o suporte da API e o acompanhamento de pedidos anteriores foram preservados.

Fonte: [meios suportados na migração para Orders](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/resources/migrate-payments-to-orders), seção “Verificar os meios de pagamento suportados”; [visão geral do Checkout Transparente](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/overview).

### Esclarecimento recebido da IA do Mercado Livre

Em 18/10/2026, o responsável compartilhou uma resposta da IA do Mercado Livre: a tarifa de débito de **1,99%, na hora, refere-se ao Point presencial**. A resposta não forneceu tarifa específica de débito para Checkout Transparente online. Essa informação esclarece o canal da tarifa anterior, mas não constitui confirmação contratual nem altera o resultado remoto de Elo.

A documentação oficial de cartões Orders foi consultada novamente via MCP. Ela confirma `payment_method.type = debit_card`; seus exemplos usam `installments` dentro de `payment_method` e valores monetários como strings (`"50.00"`). O exemplo da IA recebido nesta conversa coloca `installments` fora desse objeto e usa valores numéricos; não deve ser copiado literalmente. A implementação em `app/Services/MercadoPagoService.php` já envia o tipo solicitado, parcelas dentro de `payment_method` e valores do pedido, permitindo somente uma parcela.

A orientação geral de oferecer débito no frontend não resolve a recusa `payment_method_not_allowed_for_collector` observada na chamada direta ao provedor, que não passou pelo seletor da loja. Ainda é necessário esclarecer com o suporte a disponibilidade de `debelo` para este recebedor e estas credenciais em Orders. Não foi comprovada a existência de um botão de habilitação no painel. Débito permanece oculto em novas compras até uma validação remota bem-sucedida; nenhum novo teste remoto foi executado neste esclarecimento.

Fonte técnica: [cartões em Orders](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/payment-integration/websites/cards). Fonte comercial: resposta da IA do Mercado Livre compartilhada pelo responsável, sem verificação independente na conta.

## Cenários de titular — execução adicional em 18/10/2026

Executados diretamente em Orders com Visa oficial de teste, CPF fictício `12345678909`, R$ 50,00 e uma parcela. Todos os resultados abaixo foram confirmados por `GET /v1/orders/{id}`. Esta execução não valida o fluxo da loja nem habilitação comercial em produção.

| Titular | Order ID | Order | Detalhe do pagamento |
| --- | --- | --- | --- |
| `APRO` | `ORDTST01M5860V60PRA53HKY0B5B8YVV` | `processed/accredited` | `accredited` |
| `OTHE` | `ORDTST01M58633RN6G2CRZFQD72B185P` | `failed/failed` | `rejected_by_issuer` |
| `CONT` | `ORDTST01M5861WV4SY9RED975R8G7JDV` | `processing/in_process` | `in_process` |
| `CALL` | `ORDTST01M58636C8M2VRCM8E1E7KVD5E` | `failed/failed` | `required_call_for_authorize` |
| `FUND` | `ORDTST01M58638SG5ZF54X28DTCCE50F` | `failed/failed` | `insufficient_amount` |
| `SECU` | `ORDTST01M5863AZB6XPM8MMPXKNPQDK4` | `failed/failed` | `bad_filled_card_data` |
| `EXPI` | `ORDTST01M5863DFPEJKJAA96QP5CVFN9` | `failed/failed` | `bad_filled_card_data` |
| `FORM` | `ORDTST01M5863FNNYJHD1FMTPBZF3FVP` | `failed/failed` | `bad_filled_card_data` |

Os seis cenários recusados retornaram HTTP **402** na criação, com a Order em `data` e os motivos em `errors`. `SECU`, `EXPI` e `FORM` retornaram o mesmo detalhe genérico; não presumir distinção entre eles na API. O titular é informado na tokenização, nunca interpretado pela aplicação para aprovar ou recusar pagamentos. O traço na tabela oficial de documentos não significa enviar literalmente `-`; nesta execução foi mantido o CPF fictício em todos os cenários.

Houve repetições independentes dos cenários de recusa durante a inspeção do formato HTTP 402. As tentativas anteriores também alcançaram o provedor, mas seus IDs não foram capturados pelo extrator inicial, que procurava a Order na raiz. A tabela registra somente as tentativas com ID e consulta posterior comprovados; não corresponde ao total de Orders criadas. Não se tratou de reenvio de pedidos da loja.

**Impedimento encontrado e corrigido nesta continuação:** `MercadoPagoService::chamar()` antes aceitava somente HTTP 2xx e transformava HTTP 402 em erro 502 antes de extrair `data.id`. Agora, para criação de Orders com HTTP 402, status `failed` e ID válido em `data`, extrai somente o ID, persiste e confirma por GET autenticado. Testes cobrem preservação do ID, resultado terminal e retomada após falha da consulta sem novo POST. Respostas inválidas e falhas de rede continuam sendo resultados incertos.

A nova consulta autenticada a `/v1/payment_methods` manteve os mesmos IDs ativos do levantamento inicial, inclusive `debelo`, sem Pix no catálogo. Não foi reexecutado débito nem inferida habilitação a partir do catálogo. A busca oficial de cartões confirmou os cenários brasileiros e continuou fornecendo somente Mastercard, Visa e Amex de crédito e Elo de débito; Elo crédito permanece sem cenário oficial obtido.

Fonte: [realizar compra de teste com cartões](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/cards), consultada via busca oficial MCP. Tarifas contratuais não foram verificadas por essa ferramenta nem pela consulta de meios.

## Tarifas informadas pelo responsável em 18/10/2026

Fonte: tabela mais recente enviada pelo responsável nesta conversa. Ela substitui as informações anteriores de Pix a 0%, crédito 13x a 18x a 3,19% e Parcelado Comprador até 5,31%. Em resposta à consulta sobre o Checkout Transparente, o responsável confirmou em 18/10/2026 os valores de Pix (0,99%), crédito à vista (4,98%) e boleto (R$ 3,49). Esta confirmação é do responsável, sem verificação independente no painel ou contrato. Não foram informadas datas de início/fim de vigência e a tarifa de débito foi posteriormente confirmada pelo responsável como igual à de crédito à vista; as demais tarifas permanecem transcritas da tabela anterior.

| Modalidade | Taxa informada | Recebimento |
| --- | --- | --- |
| Crédito à vista | 4,98% | Na hora |
| Débito online | 4,98% | A confirmar |
| Crédito 2x a 6x | 2,99% | Na hora |
| Crédito 7x a 12x | 3,09% | Na hora |
| Crédito 13x a 18x | 3,09% | Na hora |
| Saldo no Mercado Pago | 4,99% | Na hora |
| Linha de Crédito | 4,99% | Na hora |
| Open Finance | 0,00% | Na hora |
| Pix | 0,99% | Na hora |
| Cartão pré-pago | 4,99% | Na hora |
| Boleto | R$ 3,49 por boleto | 3 dias |

As taxas exibidas são de **Parcelado Vendedor**; para **Parcelado Comprador**, podem chegar a 4,98%, conforme o texto enviado. Não inferir composição, acréscimo por parcela ou custo total além dos percentuais informados. O prazo de boleto foi transcrito como “3 dias”, sem indicação de dias úteis ou corridos.

O responsável confirmou em 18/10/2026 a tarifa de **4,98% para débito online**, igual ao crédito à vista. A confirmação do valor está concluída. O prazo de recebimento não foi informado. Conforme esclarecimento da IA do Mercado Livre compartilhado pelo responsável, os 1,99%, na hora, referem-se ao Point presencial. A confirmação da tarifa não elimina a recusa de Elo em Orders registrada acima.

O checkout atual permite somente uma parcela e oferece Pix, boleto e crédito em novas compras; débito está oculto no seletor até confirmar a habilitação da conta. O registro das demais modalidades e das taxas parceladas não implementa nem comprova sua habilitação na integração.

## Próximas verificações

- Credencial de teste confirmada pelo responsável; o prefixo `APP_USR` sozinho não distingue teste de produção.
- Pix: emissão com instruções e aprovação confirmadas diretamente no provedor. Validar exibição e conciliação pelo fluxo da loja.
- Boleto: emissão com instruções confirmada diretamente no provedor, inclusive sem localização extra. Validar abertura/impressão do documento no fluxo da loja; manter o ID da falha original para diagnóstico se houver recorrência.
- Crédito: validar Visa, Mastercard e Amex no checkout com tokenização segura, worker e conciliação; obter cenário oficial para Elo crédito. Registrar evidências sem PAN, CVV ou token.
- Débito: confirmar habilitação do recebedor para Elo em Orders. Não inferir suporte a Visa/Mastercard de fixtures.
- Tarifas: valores de Pix a 0,99%, crédito à vista e débito a 4,98% e boleto a R$ 3,49 confirmados pelo responsável para este checkout em 18/10/2026. Prazos registrados na tabela anterior: Pix e crédito na hora; boleto em 3 dias. Confirmar vigência contratual e prazo de recebimento do débito. Não houve verificação independente das tarifas na conta; testes de pagamento não comprovam condições comerciais.

O formulário oficial de homologação foi consultado, sem submissão. Ele solicita ramo de atividade, operação (marcas, países e contas), modalidades configuradas e recursos opcionais. Não declarar modalidades homologadas antes de executar os cenários.

## Validação local

Commit inicial: `a7dd16c` (boleto, gestão administrativa de pedidos e testes).
Resultado: 60 testes aprovados, 518 assertions. Build React aprovado com saída em `/tmp/lcb-build-homologacao`; saída habitual bloqueada por permissão em arquivo existente de `public/build`.

Na retomada da tarefa 2, `docker compose exec -T php php artisan test --filter='PixBoletoTest|MercadoPagoTest|ProcessarPedidoPagamentoTest'` aprovou 26 testes, com 195 assertions. Os testes locais continuam usando HTTP simulado; as emissões remotas da retomada estão documentadas separadamente acima.

**Relatório de implementação:** homologação parcial. Crédito Visa, Amex e Mastercard aprovado diretamente no provedor; Pix emitido com instruções e aprovado; boleto emitido com linha digitável e ticket, aguardando pagamento; débito Elo recusado para o recebedor. Fluxo completo da loja ainda pendente. Valores de Pix, crédito à vista, débito e boleto confirmados pelo responsável para este checkout; prazos registrados conforme tabela anterior. Vigência contratual, prazo de débito e disponibilidade operacional de débito ainda a confirmar, sem verificação independente no painel. Formulário não submetido. A revisão completa de segurança/qualidade permanece na pendência própria do projeto. Não há certificação nem liberação de produção neste registro.

## Continuação — API Laravel e provedor real de teste

Em 18/10/2026, `tests/Integration/MercadoPagoHomologacaoTest.php` executou requisições aos endpoints Laravel de criação e consulta, o código real do job de pagamento e chamadas autenticadas ao Mercado Pago, com SQLite em memória e dados fictícios. A fila foi interceptada para não publicar registros efêmeros no Redis da loja. A suíte não está nas suites padrão; exige a variável explícita `MP_HOMOLOGACAO_TESTE_CONFIRMADO=1`.

| Cenário | Order ID | Estado remoto / processamento local |
| --- | --- | --- |
| Pix APRO | `ORDTST01M588RXY2ZTPW388TQKAZ7EYE` | `processed` / `concluido`, pedido pago |
| Boleto | `ORDTST01M588S51W2JB3HFY0KMK14YXT` | `action_required` / `aguardando` |
| Visa APRO | `ORDTST01M588S9A02P928FV4H25QV7JS` | `processed` / `concluido`, pedido pago |
| Mastercard APRO | `ORDTST01M588SD3NQJRZD1J0PGSWX021` | `processed` / `concluido`, pedido pago |
| Amex APRO | `ORDTST01M588SH41FSEPT3PD6B9NJGCS` | `processed` / `concluido`, pedido pago |
| Visa OTHE | `ORDTST01M588SMT9XMYEHDA9C53TR4VX` | `failed` / `concluido` |
| Visa CONT | `ORDTST01M588SRWVMGAGXY7XDEYM8467` | `processing` / `aguardando` |
| Visa CALL | `ORDTST01M588SX1N31EQGSYW1C42WBQC` | `failed` / `concluido` |
| Visa FUND | `ORDTST01M588T1PJMSYG90ZAR827MNFD` | `failed` / `concluido` |
| Visa SECU | `ORDTST01M588T5QK7CR3H5ZQE8WWCBP0` | `failed` / `concluido` |
| Visa EXPI | `ORDTST01M588T9KK8RM96X5JQ037E1SV` | `failed` / `concluido` |
| Visa FORM | `ORDTST01M588TDJ8WN406N8RRSCBAY74` | `failed` / `concluido` |
| Débito Elo | Sem ID capturado | Falha local 502 ao chamar o provedor; não homologado |

Foram verificados campos de Pix (`ticket_url`, `qr_code`, `qr_code_base64`) e boleto (`ticket_url`, `barcode_content`, `digitable_line`), consulta pela API local, apagamento do snapshot de envio após obter o ID, repetição idempotente da solicitação e chamada local assinada do webhook consultando a Order real. Boleto continua sem compensação, e não foi declarado pago. As recusas não alteraram o pedido para pago.

A primeira execução parou no Pix pendente: faltava `payer.first_name`, exigido para a aprovação automática do cenário oficial `APRO`. O serviço passou a incluir nome/sobrenome do snapshot fiscal, quando disponíveis, sem reconhecer códigos de teste nem forçar aprovação. A execução posterior acima passou: **1 teste, 170 assertions**. As tentativas da primeira execução não foram convertidas em sucesso nem reutilizadas como pedidos locais.

Limites: esta execução não testou navegador, iframes interativos, OAuth real, workers Redis/Horizon, scheduler real ou entrega automática do webhook. Os testes locais separados cobrem esses contratos com simulações, mas não substituem evidências operacionais. Nenhum dado foi criado no PostgreSQL da loja nem enviado ao provedor fiscal. Build React aprovado com saída temporária `/tmp/lcb-build-homologacao-final`. Após as alterações finais, a suíte local de pagamentos passou com **58 testes e 511 assertions**; coleção Postman com JSON válido e `git diff --check` sem erros.

### Formulário e avaliação oficial

Aplicação principal retornada por `application_list`: `880483999981556`. A aplicação associada às Orders de teste é `1044824833239556`. A tentativa de avaliação com esta última foi recusada por validação de propriedade OAuth; com a principal, a ferramenta retornou erro interno de consulta de Orders por ausência de `product_id`, parâmetro não exposto na assinatura de `quality_evaluation`. Não há nota oficial de qualidade obtida.

O responsável confirmou varejo, uma marca (LCB), Brasil e uma conta. O formulário foi preenchido com esses dados e crédito/Pix/boleto, excluindo débito e funcionalidades não implementadas. A ferramenta retornou o campo obrigatório adicional `ou_api_integration_model` (declaração de conformidade PCI DSS). **Não houve submissão concluída.** A declaração permanece sem resposta; a avaliação inicial está em [PCI-DSS.md](PCI-DSS.md). O uso de iframes e os testes aprovados não permitem declarar conformidade por inferência.

`notifications_history` da aplicação principal não retornou notificações; a resposta não distingue ausência de configuração de ausência de envios. O diagnóstico local encontrou as três credenciais presentes e APP_URL local. O túnel ngrok estava ativo, mas a entrega automática assinada não foi comprovada nesta etapa.

**Situação atual:** fluxo da API e execução do job validados com provedor real de teste para Pix, boleto e crédito Visa/Mastercard/Amex, incluindo oito resultados de titular. Recusas HTTP 402 corrigidas. Débito continua não homologado. Homologação formal e liberação de produção não concluídas: faltam declaração PCI, avaliação oficial disponível, verificação em navegador e entrega automática de webhooks.

## Diagnóstico operacional de webhook e Horizon — 18/10/2026

O túnel encaminhou o POST de diagnóstico sem assinatura para `/api/webhooks/mercado-pago`; o receptor registrou HTTP 403, conforme esperado. A configuração do ngrok foi preservada nesta execução.

Executado `scripts/homologar-webhook.php preparar webhook-homolog-4df02187-fde8-426e-9a3a-c19bc29fe42a`, com ambiente local e confirmação explícita de credenciais de teste. O script criou registros fictícios persistentes, produto inativo e snapshot com processamento fiscal desativado apenas para esse diagnóstico. Não altera estoque de produtos da loja nem executa exclusões físicas. A primeira tentativa foi revertida integralmente por limite de tamanho da chave do pedido, antes da criação de qualquer cobrança; o script foi ajustado para usar o UUID de 36 caracteres.

Pedido local **6**, Order **`ORDTST01M589JSDX0DH4ZPJA6KJZGB2R`**, Pix fictício de R$ 50,00: passou de solicitação `pendente` para pagamento `processed/concluido` e pedido `pago`, processado pelo Horizon real. O processamento fiscal permaneceu desativado. A consulta do script é somente leitura; `preparar` reutiliza a mesma referência e tentativa, sem criar novo pedido ou nova cobrança.

As notificações automáticas dessa Order chegaram ao caminho correto, mas retornaram **403**. Comparação em memória da assinatura recebida com `MERCADO_PAGO_WEBHOOK_SECRET` do `.env` não conferiu, tanto com o ID original quanto em minúsculas. Assinatura e `x-request-id` estavam presentes. Valores secretos e assinaturas não foram registrados no relatório. O responsável foi orientado a conferir o segredo da aplicação de teste no painel e atualizar o `.env`, sem compartilhá-lo no chat.

Conclusão desta etapa: Horizon e conciliação sem navegador comprovados para esse Pix; entrega automática observada, porém webhook ainda não validado com HTTP 200. O status pago veio da conciliação pelo worker, e não comprova aceitação do webhook. Preservada a rejeição de assinaturas inválidas.

## Reteste após redefinição do segredo — 18/10/2026

Nova execução completa dos contratos de pagamento: **58 testes locais aprovados, 511 assertions**; execução da API/job contra o provedor real de teste: **1 teste aprovado, 170 assertions**. Pix aprovado (`ORDTST01M58CZ9NXC3G9MHGMYN0SWG2X`), boleto emitido (`ORDTST01M58CZGMZ7KCS1RZP9KQ34WS4`), crédito Visa (`ORDTST01M58CZMQB3N489C5TEAFFTGBZ`), Mastercard (`ORDTST01M58CZRP9DJRXSFD8FVTZ1F9N`) e Amex (`ORDTST01M58CZWFP912R6FR6Q9CMMESA`) aprovados; oito cenários de titular com resultados esperados, incluindo pendência e seis recusas. Débito Elo voltou a falhar na criação, reportado como não homologado.

Novo diagnóstico persistente: pedido **7**, Order **`ORDTST01M58CXHYDNJ3Z6YS53SVEB0X5`**, Pix de teste R$ 50,00, processado pelo Horizon e confirmado como pago. Sem emissão fiscal, com configuração sem cache e segredo carregado igual ao `.env`.

A notificação do simulador da aplicação principal com ID fictício `123456` teve assinatura válida com o segredo redefinido. A consulta desse ID falhou e o receptor retornou 502. Isso comprovou a assinatura do simulador, sem comprovar a assinatura das notificações automáticas de pagamentos.

As novas notificações automáticas trazem explicitamente `application_id=1044824833239556` e `live_mode=false`. Seus IDs no corpo e na query coincidem. A assinatura não corresponde ao segredo atual, tanto com ID original quanto em minúsculas; as entregas retornam 403. A aplicação principal **880483999981556** continua sendo a aplicação alvo: a consulta autenticada de suas credenciais confirmou que o vendedor/aplicação de teste **1044824833239556** é fornecido automaticamente. A diferença de IDs, isoladamente, não caracteriza erro de escolha de credenciais. É necessário alinhar o segredo do emissor das notificações automáticas; a correspondência observada no simulador da principal não valida esse emissor.

Duas chamadas públicas controladas, assinadas localmente com o segredo atual e referenciando a mesma Order real do pedido 7, retornaram **200** no registro do ngrok, incluindo a repetição do evento. Esse teste usa assinatura local, não gerada pelo Mercado Pago; comprova que receptor, assinatura local e consulta autenticada funcionam, mas não substitui a aceitação das notificações automáticas do provedor.

Sem alterações no ngrok, credenciais ou infraestrutura neste reteste. Verificação em navegador/iframes, declaração PCI e aceite das notificações automáticas continuam fora dos resultados aprovados; boleto não foi declarado compensado. Registros fictícios persistentes permanecem preservados.

## Verificação final solicitada — 18/10/2026

Criado o diagnóstico persistente `webhook-homolog-d75bcce1-6ab2-460b-b11c-ed8faea10398`, pedido **8**, para testar uma nova notificação automática após a redefinição do segredo. Order **ORDTST01M58DZ0G8714VFG8D5TTRQKCJ**: notificação automática observada em 18:16:31 (Brasília), HTTP **403**, assinatura divergente do segredo atual. A aceitação automática ainda não foi homologada; chamadas controladas com HTTP 200 e simulador com assinatura válida não substituem essa verificação.

Os eventos anteriores com assinatura válida foram identificados: HTTP 502 para `type=order` e ID fictício `12345`, enviado pelo simulador da aplicação principal; HTTP 422 para `type=stop_delivery_op_wh`, não aceito pelo receptor exclusivo de Orders. Esses resultados não demonstram falha do ngrok nem aprovação das notificações automáticas.

Na comparação específica de transporte anterior, hashes de `data.id`, `x-request-id`, `x-signature` e corpo de uma notificação automática repetida permaneceram idênticos entre a captura do ngrok e a chegada ao Laravel. A instrumentação foi removida. Não foi identificada alteração desses campos pelo túnel.

Homologação permanece parcial: fluxo da API e Horizon validado; assinatura automática da aplicação de teste ainda divergente, débito não homologado, navegador e declaração PCI ainda sem conclusão. Não foi submetida declaração de conformidade nem alterada a proteção HMAC.

## Verificação do ambiente e novo pedido — 18/10/2026

Consulta autenticada `GET /users/me` com o Access Token atual confirmou usuário **3770562867**, site **MLB** e tag **test_user**. A aplicação usa `https://api.mercadopago.com/v1/orders`; o ambiente de teste é determinado pelas credenciais. A consulta da Order anterior retornou ID ORDTST e aplicação de teste **1044824833239556**; o GET de Orders não retornou campo `live_mode` nessa resposta, portanto essa confirmação adicional foi obtida da notificação automática abaixo.

Criado novo pedido fictício **11**, referência `webhook-homolog-3ddf21ab-8a01-4872-95bb-149ac645aa38`, Order **ORDTST01M58FM0Q0NAWN8CXCT7K0H58G**, Pix R$ 50,00. A notificação automática declarou **live_mode=false**, **application_id=1044824833239556** e retornou **403** em 18:45:27 (Brasília). A assinatura não corresponde ao segredo atual nem com ID original nem em minúsculas. Isso confirma execução em teste; não se trata de envio de pagamento produtivo. Segredo fornecido pelo responsável foi aplicado somente no .env, sem registro de seu valor neste relatório.

## Chamado de suporte — 18/10/2026

O responsável informou a abertura de chamado pelo atendimento do Mercado Pago, produto Checkout API, tema “Webhook Orders (teste): HMAC de x-signature não confere para aplicação vendedora automática”. A confirmação e o número no formato WCS-XXXXX serão enviados por e-mail; o número efetivo ainda não foi fornecido. Não registrar WCS-XXXXX como número real.

O chamado inclui a relação entre a aplicação principal 880483999981556 e a vendedora automática 1044824833239556, o exemplo da Order ORDTST01M58FM0Q0NAWN8CXCT7K0H58G, os campos recebidos e os manifests testados, sem segredos. O suporte deverá esclarecer qual segredo assina as notificações automáticas de teste e eventual divergência com a documentação. O código atual preserva a caixa do ID; a versão em minúsculas foi testada separadamente no diagnóstico, também sem correspondência.

Situação: aguardar resposta técnica para concluir a validação das notificações automáticas. A abertura do chamado foi informada pelo responsável, sem verificação independente do protocolo. Não houve conclusão da homologação nem liberação de produção. Manter HMAC e verificações financeiras ativos; evitar novas cobranças de teste sem alteração que justifique a repetição.

Acompanhamento: [Central de atendimento Mercado Pago](https://www.mercadopago.com.br/developers/pt/support/center/tickets?status=all#from=/mp_wcs/list_all_tickets).

## Alinhamento do validador PHP — 18/10/2026

Após nova troca do segredo pelo responsável, o receptor foi alinhado à normalização da implementação PHP oficial consultada: trim no ID e request-id; trim e chaves em minúsculas no parser de x-signature; HMAC-SHA256 e hash_equals preservados. ID e request-id permanecem obrigatórios para o receptor específico de Orders. A caixa do ID foi preservada conforme o código executável do SDK, apesar da divergência com o comentário/documentação de minúsculas. O ID normalizado é também usado na consulta da Order.

Falha de autorização passou de HTTP 403 para **401**, sem consulta ao provedor e sem expor detalhes técnicos. A consulta autenticada da Order continua ocorrendo antes do HTTP 200; não foi introduzido aceite incondicional. Coleção Postman e instruções atualizadas.

Validação direcionada: **4 testes aprovados, 13 assertions**, cobrindo assinatura ausente, normalização de espaços/chaves, timestamp/hash inválidos e webhook válido idempotente. Três notificações automáticas capturadas foram comparadas com o segredo atual; nenhuma correspondeu com ID original ou em minúsculas. Uma foi reenviada ao endpoint público atualizado e retornou **401**. Nenhuma nova cobrança foi criada nesta etapa. Eventos capturados anteriormente não comprovam o efeito de eventual redefinição do segredo no emissor depois de sua emissão.

## Estados da página de pagamento — 18/10/2026

Revisão local sem novas cobranças e sem alterações no backend. A página agora distingue preparação das instruções, pagamento pendente, recusa, expiração, cancelamento, estorno, contestação e confirmação. A confirmação depende do status do pedido retornado pela API. Em verificação, as instruções anteriores ficam ocultas e a consulta automática para; a atualização manual continua disponível. Recusas definitivas não oferecem outra cobrança, conforme a restrição atual de novas tentativas.

Pix orienta conferir valor e destinatário no banco. Boleto orienta conferir beneficiário, valor e vencimento e explica que a emissão não comprova pagamento. O link de impressão continua restrito a HTTPS e abre outra aba com `noopener noreferrer`. Instruções ficam ocultas após encerramento ou confirmação.

Validação automatizada: **21 testes aprovados**, incluindo renderização do HTML da página React com respostas fictícias, sem chamar o provedor. Comando: `docker compose exec -T node node --test tests/Frontend/estadoPagamento.test.mjs tests/Frontend/pagamentoRender.test.mjs`. Esses testes não executam clipboard, layout, SDK/iframes nem impressão do documento do provedor e não comprovam habilitação de débito.

Roteiro pendente no navegador, usando somente conta e dados de teste e preservando os registros:

| Cenário | Verificação esperada |
| --- | --- |
| Solicitação ainda na fila | “Preparando pagamento” para Pix/boleto; sem confirmação e sem botão para criar outra cobrança. |
| Pix emitido | QR legível, código completo e somente leitura; copiar e comparar com o campo; em falha de clipboard, seleção manual disponível; conferir instruções no celular. |
| Boleto emitido | Linha completa, somente leitura e copiável; abrir documento HTTPS em outra aba; conferir pedido, valor, beneficiário e vencimento; abrir impressão/visualização e salvar PDF; emissão continua pendente. |
| Cartão pendente | Acompanhamento sem declarar aprovação; recarregar e retornar pelo pedido sem duplicar cobrança. |
| Recusa definitiva | “Pagamento recusado”, orientação para contato e ausência de nova tentativa; não expor motivo técnico do provedor. |
| Confirmação pela API | “Pagamento confirmado”, sem códigos antigos ou botões de cobrança; conferir também o pedido. |
| Expiração ou verificação | Expirado não oferece códigos anteriores; verificação orienta contato e oculta instruções; não induzir outro pagamento. |
| Mobile e desktop | Campos sem corte, códigos selecionáveis, foco de teclado visível e mensagens legíveis; testar retorno da aba do boleto. |

Registrar cenário, modalidade, pedido/Order de teste, resultado e evidência sem dados sensíveis. A impressão real, as interações e a inspeção visual em navegador permanecem pendentes; não marcar a tarefa integral como concluída com base no HTML automatizado.

## Diagnóstico criptográfico do boleto — 19/10/2026

Analisada a notificação automática do boleto do pedido **15**, Order **ORDTST01M5AE4AWG3F8MTP7B7MWSR1VT**, request-id **a88fbd68-a09d-49e2-9ed0-09d9dee6afb1**. O boleto foi emitido com instruções e permanece aguardando pagamento. O webhook chegou ao Laravel, que rejeitou a assinatura.

Diagnóstico executado em PHP no ambiente local, sem nova cobrança e sem alterar o receptor:

- Vetor conhecido HMAC-SHA256 RFC 4231 aprovado.
- Segredo carregado pelo Laravel idêntico ao .env, sem cache, espaços externos ou BOM.
- Query normalizada pelo PHP em data_id coincide com data.id do corpo; timestamp numérico e v1 hexadecimal de 64 caracteres.
- O FormRequest real rejeitou a notificação capturada, mas aceitou um controle sintético com assinatura calculada localmente usando os mesmos campos.
- **4.612 variações** finitas de campos, ordem, caixa, separadores, terminações, segundos/milissegundos, HMAC do JSON e interpretações texto/hex/binário/Base64 da chave não produziram correspondência. As variantes foram somente diagnósticas, sem alteração das regras de aceitação.
- Em **97 notificações** capturadas, não foi encontrada assinatura reutilizada com request-id ou timestamp diferentes. Dois grupos tinham assinatura repetida, sem divergência desses campos.

A implementação oficial do SDK PHP foi comparada por leitura do código. Sua execução baixada em processo com acesso ao segredo foi rejeitada pela revisão automática por risco de código externo; essa ação não foi executada e não foi contornada. O diagnóstico concluído usa somente código local e funções nativas. Capturas e scripts temporários privados foram removidos após a análise.

Conclusão: não foi demonstrado erro de cálculo, leitura ou transporte nas hipóteses testadas. Não é possível recuperar o segredo a partir de HMAC nem inferir o manifest correto apenas da ausência de correspondências. Permanecem hipóteses externas: segredo efetivo do emissor diferente ou cálculo do provedor diferente do contrato. Solicitar ao suporte confirmação do manifest e da associação do segredo para o request-id acima, sem pedir nem divulgar segredos em mensagem.

## Aceite do boleto digital — 19/10/2026

O responsável aprovou o boleto digital do Mercado Pago e declarou essa entrega homologada: “O boleto digital do mercado pago está perfeito, pode entender como homologado”. Aceite registrado após o ajuste visual da linha digitável: fundo azul claro, texto contrastante, borda vinho, cantos arredondados, altura fixa e botão de cópia dentro do campo.

Este aceite atualiza a pendência de apresentação do boleto descrita no roteiro anterior. A homologação do boleto digital está concluída pelo responsável. Não foram fornecidas evidências individuais de impressão ou clipboard; o aceite é a confirmação funcional do responsável. Compensação do boleto, aceite automático de webhooks, demais modalidades e declaração PCI permanecem nos seus respectivos acompanhamentos.

## SDK oficial instalado e integrado — 19/10/2026

Após autorização explícita do responsável para baixar e usar o SDK, instalado pelo Composer **mercadopago/dx-php 3.16.0**, referência **6138da3381549e4d0a5839440e6f0e35d1760e7a**, sem atualização de outras dependências e sem scripts/plugins de instalação. A configuração de otimização do projeto foi preservada; o autoload local foi gerado sem otimização para evitar a varredura demorada do volume. Composer validou manifesto e lock.

`MercadoPagoWebhookRequest` agora chama diretamente `MercadoPago\Webhook\WebhookSignatureValidator::validate`, passando o header x-signature inteiro, x-request-id, data_id e o segredo configurado. O cálculo próprio e o parser próprio foram removidos. O receptor continua exigindo ID válido e request-id, retorna 401 em falha e consulta a Order autenticada após sucesso. Chamadas de Orders continuam no cliente HTTP Laravel.

Teste direto do SDK instalado sobre três notificações capturadas, sem nova cobrança:

- Boleto: ORDTST01M5AE4AWG3F8MTP7B7MWSR1VT / request-id a88fbd68-a09d-49e2-9ed0-09d9dee6afb1: SignatureMismatch.
- Crédito: ORDTST01M58NRFWSV73GNYGYZ0GNT61Q / request-id 3d89fec7-f321-4067-aaf0-d1e420e78cf2: SignatureMismatch.
- Pix: ORDTST01M58JKEEHA5H39VVR0C64RVZC / request-id 0bef3f4d-a8c5-481e-bff9-afc30d02b49f: SignatureMismatch.

Todos os casos foram enviados ao SDK com ID original e em minúsculas, com o mesmo resultado. O replay da notificação do boleto no receptor público já usando o SDK retornou 401. A suíte direcionada passou com 8 testes e 69 assertions, incluindo campos obrigatórios, assinaturas inválidas, normalização, idempotência e Pix/boleto. Capturas e scripts privados temporários removidos após o teste. O SDK aceitou um controle sintético assinado localmente. Portanto, a falha das capturas é reproduzível com o validador oficial, não apenas com a implementação local anterior. Isso não determina qual segredo efetivo foi usado pelo emissor nem comprova erro do provedor. A declaração de conformidade e a homologação automática permanecem pendentes.

## Revalidação dos estados de apresentação — 19/10/2026

Executados os testes `tests/Frontend/estadoPagamento.test.mjs` e `tests/Frontend/pagamentoRender.test.mjs` no container Node: **29 testes aprovados**. A matriz de HTML agora verifica pendência, recusa e confirmação para Pix, boleto, crédito e débito, incluindo ausência de nova cobrança em pendência e remoção de instruções antigas após recusa/confirmação. A cobertura de boleto verifica linha somente leitura, ação de cópia e link de impressão HTTPS; protocolos inseguros são rejeitados.

Corrigida a apresentação de instruções prematuras: enquanto Pix/boleto não têm código, QR ou documento utilizável, a tela mantém o estado de preparação sem orientar o cliente a usar dados ainda ausentes. Nenhuma API, credencial ou cobrança foi alterada nesta etapa.

Não havia ferramenta de navegador disponível nesta sessão. Os resultados são de estado e HTML renderizado com respostas fictícias; não comprovam layout mobile/desktop, clipboard, leitura real do QR, impressão, SDK de cartões nem transições interativas. O aceite anterior do boleto permanece registrado; a tarefa integral continua pendente dos demais cenários do roteiro em navegador. A cobertura de débito valida somente a apresentação de pedidos existentes, sem confirmar habilitação da modalidade.

## Recuperação do carregamento de cartão — 19/10/2026

O responsável relatou timeout e campos incompletos ao abrir débito. Crédito e débito compartilham o mesmo CardForm. Foi ampliado o limite de montagem dos iframes de 15 para 30 segundos, com temporizador armado antes da inicialização e cancelado quando os campos ficam prontos ou ocorre falha. Timeout desmonta a instância; callbacks tardios dessa tentativa não liberam pagamento. Falha na consulta dos tipos de documento recebe mensagem específica. Um botão permite remontar os campos sem criar cobrança, preservando a exigência dos três campos seguros antes de liberar o envio.

Validação local: 36 testes de frontend aprovados, incluindo seis cenários com SDK/DOM controlados para carregamento, timeout, recuperação e campos incompletos. A causa específica do relato não foi reproduzida com SDK real no navegador; a alteração trata tolerância e recuperação do carregamento. Habilitação de débito e aprovação pelo provedor continuam pendentes de homologação. Referência do ciclo de montagem: [CardForm oficial](https://github.com/mercadopago/sdk-js/blob/main/docs/card-form.md), callbacks `onReady` e `onIdentificationTypesReceived`.

### Complemento do relato: tipo de documento no débito

O responsável esclareceu que o campo incompleto é o tipo de documento e que o problema não ocorreu na validação de crédito do dia anterior. A causa permanece sem reprodução com SDK real. A verificação local agora rejeita resposta vazia de tipos de documento e seletor sem opções, mesmo com iframes prontos; timeout diferencia a ausência de documentos da demora dos campos seguros. Nove testes direcionados aprovados com SDK/DOM simulados. Retestar débito no navegador e, se persistir, verificar a resposta da consulta de documentos do SDK, sem registrar credenciais ou dados do titular.

### Diagnóstico da consulta de documentos — 19/10/2026

Após novo relato de seletor vazio, a inspeção HTTP confirmou que o HTML usa Vite e o módulo servido contém a versão atual do hook. Não foi identificado build antigo nessa consulta local.

Consulta remota somente leitura a `/v1/identification_types`: com `public_key` configurada e normalizada, HTTP **400**, mensagem **`invalid id`**; com Access Token do servidor, HTTP **200**, tipos **CPF e CNPJ**. Credenciais e documentos pessoais não foram registrados, e nenhuma cobrança foi criada. Esse resultado aponta para a configuração/autenticação pública usada na consulta de documentos; o retorno com token não valida a chave pública nem comprova a causa no navegador. Conferir `MERCADO_PAGO_PUBLIC_KEY` da mesma aplicação/conta de teste e retestar o SDK. Aumentar timeout não corrige HTTP 400. Preservado o relato de crédito funcional na validação anterior.

### Correção da chave pública via MCP — 19/10/2026

Por solicitação do responsável, usados `application_list` e `get_credentials` do MCP Mercado Pago para a aplicação principal **880483999981556**. O MCP confirmou as credenciais de teste da conta vendedora automática **3770562867**, aplicação **1044824833239556**. Comparação sem expor valores: Access Token local igual ao retornado pelo MCP; chave pública local divergente.

Atualizada somente `MERCADO_PAGO_PUBLIC_KEY` no `.env`, preservando Access Token e segredo de webhook. O `.env` permanece ignorado pelo Git. Configuração Laravel sem cache nesta verificação. Nova consulta pública a `/v1/identification_types` retornou **HTTP 200**, com **CPF e CNPJ**, em vez do HTTP 400 anterior. Nenhuma cobrança foi criada. Reabrir/recarregar a tela de pagamento para buscar a configuração pública atual; o reteste visual do débito ainda depende dessa interação no navegador. Manter credenciais protegidas e fora do versionamento.

### Aceite visual do formulário de débito — 19/10/2026

Após a correção da chave pública, o responsável confirmou: “Visualmente ok, consegui colocar a compra online nos parâmetros ditos”. Registrado o aceite da apresentação e do preenchimento do formulário de débito com os parâmetros informados. Este relato conclui o reteste visual do formulário que estava com o tipo de documento ausente. Não foi informado o resultado financeiro da tentativa; aprovação do débito e estados posteriores de pagamento permanecem pendentes de confirmação.

## Cronômetro de pagamento — 19/10/2026

Adicionados cronômetro e barra regressiva à tela de pagamento e aos detalhes do pedido pendente. As instruções cifradas preservam `criado_em` e `expira_em`, provenientes de `created_date` da Order e `transactions.payments.0.date_of_expiration`. Datas omitidas em consultas posteriores preservam os prazos já recebidos. Nenhum prazo novo é definido no provedor.

O relógio recalcula o tempo absoluto a cada segundo e ao retornar à aba, sem reiniciar em recargas. A barra acompanha a proporção restante desde a emissão. Após o vencimento, mostra zero e orienta aguardar a atualização do pedido; confirmação, cancelamento e autorização continuam no backend. Sem vencimento informado, apresenta tempo decorrido de espera, sem prazo fictício nem barra regressiva. Estados finais e verificação não exibem contagem.

Consulta somente leitura de três Orders existentes confirmou datas de expiração retornadas pelo provedor para Pix e boleto; nenhuma cobrança foi criada nesta etapa. Validação: **21 testes de frontend aprovados**, **3 testes de API aprovados (56 assertions)** e build aprovado em `/tmp/lcb-tempo-pagamento-build`. Cobertura de contagem, proporção, vencimento, datas ausentes/inválidas, preservação na conciliação e renderização. Referência: [consulta de Orders](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api/get-order/get).

## Revisão consolidada — 19/10/2026

Nova revisão em [MERCADO-PAGO-REVISAO.md](MERCADO-PAGO-REVISAO.md): 63 testes PHP aprovados (524 assertions) e 45 testes de frontend aprovados. Consulta remota somente leitura de oito Orders existentes confirmou os estados locais: Pix e Mastercard aprovados, boleto do pedido 15 aguardando pagamento e Pix do pedido 17 aguardando transferência. Nenhuma Order de débito nessa amostra. Nenhuma nova cobrança criada ou registro alterado.

O responsável reconfirmou varejo, uma marca LCB, Brasil, uma conta Mercado Pago e os quatro meios configurados. O formulário oficial continua exigindo declaração PCI após selecionar cartões; essa resposta não foi presumida. Não houve submissão concluída. Checklist oficial integralmente classificado no novo relatório, com ressalvas de aplicabilidade a Orders. Mantidas as pendências de assinatura automática, débito e matriz interativa; preservado o aceite anterior do boleto digital.

## Atualização do formulário oficial — 19/10/2026

Após a revisão consolidada, o responsável respondeu “Declarar que ainda não cumpre o padrão”. Enviado `ou_api_integration_model=no` com os dados operacionais confirmados e Pix/boleto/crédito/débito configurados. O MCP retornou `Form saved successfully`, formulário **16400**, aplicação **880483999981556**, produto 24, `needs_review=false`. O salvamento conclui a submissão do formulário; não comprova conformidade PCI, aprovação financeira do débito, aceitação dos webhooks ou liberação para produção. Essas pendências permanecem. Nenhuma cobrança foi criada.

## Fontes oficiais

- [Consulta de meios de pagamento](https://www.mercadopago.com/developers/pt/docs/checkout-bricks/additional-content/consult-payment-methods)
- [Pix em Orders e pré-requisito de chave cadastrada](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/payment-integration/websites/pix)
- [Cenário de teste Pix](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/pix)
- [Cenário de teste boleto](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/boleto)
- [Cartões oficiais de teste](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/cards)
- [Condições comerciais na conta](https://www.mercadopago.com.br/costs-section#from-section=menu)
