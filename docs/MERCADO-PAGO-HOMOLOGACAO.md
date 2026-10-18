# Homologação Mercado Pago

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
| Pix, cenário oficial `APRO` | `ORDTST01M56VDW0W9181HWRA0G3WMGN6` | `processing/in_process`, sem QR Code nem ticket |
| Boleto, CPF de teste e endereço completo | `ORDTST01M56VEFB4NT5891KSHPGAPE55` | Order `failed/failed`; pagamento `failed/processing_error`, sem linha digitável nem ticket |
| Crédito Visa | `ORDTST01M56VFC2VYZETWFAXESYM850N` | `processed/accredited`, total pago R$ 50,00 |
| Crédito Amex | `ORDTST01M56VFE7EZNRKVW3F92256KPM` | `processed/accredited`, total pago R$ 50,00 |
| Crédito Mastercard | `ORDTST01M56VH1K24JCE1A9YYKE4YT3R` | `processed/accredited`, total pago R$ 50,00 |
| Débito Elo (`debelo`) | Não criada | HTTP 400, `payment_method_not_allowed_for_collector` |

Pix foi criado às 06:33:17 UTC (03:33:17 de Brasília), boleto às 06:33:36 UTC (03:33:36 de Brasília). O boleto passou a falha às 06:34:44 UTC. As consultas posteriores não retornaram causa mais específica que `processing_error`. Não interpretar isso como problema comprovado no endereço ou na implementação da loja. Pix ainda pendente também não comprova aprovação nem indisponibilidade definitiva. Consultar os mesmos IDs, sem recriar cobranças para resolver resultado incerto.

Os cartões utilizados são os exemplos oficiais brasileiros para Mastercard, Visa, Amex e Elo débito, titular `APRO`, validade 11/2030. A tabela oficial consultada não forneceu cartão Elo crédito para o Brasil; não foi inventado um número para esse cenário. As aprovações remotas de crédito não comprovam habilitação comercial em produção.

O catálogo geral anunciou débito Elo como ativo, mas a criação em Orders foi recusada para o recebedor. Confirmar a habilitação específica com o Mercado Pago antes de considerar débito homologado. O identificador `boleto` foi aceito por Orders; a criação assíncrona terminou em falha, portanto emissão não homologada.

## Próximas verificações

- Credencial de teste confirmada pelo responsável; o prefixo `APP_USR` sozinho não distingue teste de produção.
- Pix: consultar a Order existente até o estado final e investigar ausência das instruções. Validar também o fluxo da loja.
- Boleto: investigar `processing_error` com o provedor usando o ID registrado; ainda falta emissão de linha digitável e documento. O teste oficial de boleto verifica emissão e espera de pagamento, sem exigir compensação real para homologar esse cenário.
- Crédito: validar Visa, Mastercard e Amex no checkout com tokenização segura, worker e conciliação; obter cenário oficial para Elo crédito. Registrar evidências sem PAN, CVV ou token.
- Débito: confirmar habilitação do recebedor para Elo em Orders. Não inferir suporte a Visa/Mastercard de fixtures.
- Tarifas: consultar a seção **Taxas e parcelas** da conta vendedora, registrando modalidade, prazo de recebimento, percentual/valor fixo e data de vigência. A resposta consultada não informa tarifas. Valores públicos e testes não comprovam condições comerciais da conta.

O formulário oficial de homologação foi consultado, sem submissão. Ele solicita ramo de atividade, operação (marcas, países e contas), modalidades configuradas e recursos opcionais. Não declarar modalidades homologadas antes de executar os cenários.

## Validação local

Commit inicial: `a7dd16c` (boleto, gestão administrativa de pedidos e testes).
Resultado: 60 testes aprovados, 518 assertions. Build React aprovado com saída em `/tmp/lcb-build-homologacao`; saída habitual bloqueada por permissão em arquivo existente de `public/build`.

**Relatório de implementação:** homologação parcial. Crédito Visa, Amex e Mastercard aprovado diretamente no provedor; Pix pendente; boleto falhou; débito Elo recusado para o recebedor. Tarifas não confirmadas; formulário não submetido. A revisão completa de segurança/qualidade permanece na pendência própria do projeto. Não há certificação nem liberação de produção neste registro.

## Fontes oficiais

- [Consulta de meios de pagamento](https://www.mercadopago.com/developers/pt/docs/checkout-bricks/additional-content/consult-payment-methods)
- [Pix em Orders e pré-requisito de chave cadastrada](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/payment-integration/websites/pix)
- [Cenário de teste Pix](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/pix)
- [Cenário de teste boleto](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/boleto)
- [Cartões oficiais de teste](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/cards)
- [Condições comerciais na conta](https://www.mercadopago.com.br/costs-section#from-section=menu)
