# Homologação Mercado Pago

## Levantamento de 18/10/2026

Integração: Checkout Transparente, Orders API, Brasil, BRL, uma parcela.
Consulta remota autenticada: `GET https://api.mercadopago.com/v1/payment_methods`, usando a credencial do `.env`, sem registrar seu valor. Nenhuma cobrança foi criada neste levantamento.

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

## Próximas verificações

- Confirmar que a credencial configurada veio da seção **Credenciais de teste**. O prefixo `APP_USR` não distingue teste de produção.
- Pix: executar o cenário oficial com `payer.first_name=APRO`, criar Order com chave de idempotência e consultar até o estado final. Validar também o fluxo da loja; o teste direto do provedor não comprova integração ponta a ponta.
- Boleto: emitir com comprador de teste, identificação e endereço válidos; conferir linha digitável, documento e evolução do estado.
- Crédito: repetir com cartões oficiais de teste Visa, Elo e Amex; manter evidência da Mastercard já mencionada no projeto. Registrar ID, data, status e resultado da conciliação, sem PAN, CVV ou token.
- Débito: verificar o cenário oficial compatível com Elo e tokenização. Não inferir suporte a Visa/Mastercard de fixtures.
- Tarifas: consultar a seção **Taxas e parcelas** da conta vendedora, registrando modalidade, prazo de recebimento, percentual/valor fixo e data de vigência. A resposta consultada não informa tarifas. Valores públicos e testes não comprovam condições comerciais da conta.

O formulário oficial de homologação foi consultado, sem submissão. Ele solicita ramo de atividade, operação (marcas, países e contas), modalidades configuradas e recursos opcionais. Não declarar modalidades homologadas antes de executar os cenários.

## Validação local

Commit inicial: `a7dd16c` (boleto, gestão administrativa de pedidos e testes).
Resultado: 60 testes aprovados, 518 assertions. Build React aprovado com saída em `/tmp/lcb-build-homologacao`; saída habitual bloqueada por permissão em arquivo existente de `public/build`.

**Relatório de implementação:** levantamento parcial. Modalidades consultadas; tarifas não confirmadas; Orders remotas ainda não criadas; formulário não submetido. A revisão completa de segurança/qualidade permanece na pendência própria do projeto. Não há certificação nem liberação de produção neste registro.

## Fontes oficiais

- [Consulta de meios de pagamento](https://www.mercadopago.com/developers/pt/docs/checkout-bricks/additional-content/consult-payment-methods)
- [Pix em Orders e pré-requisito de chave cadastrada](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/payment-integration/websites/pix)
- [Cenário de teste Pix](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/integration-test/pix)
- [Condições comerciais na conta](https://www.mercadopago.com.br/costs-section#from-section=menu)
