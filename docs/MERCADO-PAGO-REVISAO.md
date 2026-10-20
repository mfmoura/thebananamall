# Revisão de homologação — 19/10/2026

Homologação parcial. Esta revisão não certifica a integração nem libera produção. Evidências anteriores de transações estão em [MERCADO-PAGO-HOMOLOGACAO.md](MERCADO-PAGO-HOMOLOGACAO.md). Nenhuma nova cobrança foi criada nesta revisão: permanece a orientação registrada de aguardar o suporte sobre as assinaturas automáticas.

Consulta remota somente leitura das oito Orders de teste mais recentes: estados locais e remotos coincidiram. Pix do pedido 16 (`ORDTST01M5AJ38QQNSJP5MXTTTR7F571`) e Mastercard do pedido 14 (`ORDTST01M58NRFWSV73GNYGYZ0GNT61Q`) estão `processed/accredited`, com pedidos pagos. Pix do pedido 17 está `action_required/waiting_transfer`; boleto do pedido 15 (`ORDTST01M5AE4AWG3F8MTP7B7MWSR1VT`) está `action_required/waiting_payment`. Pedidos 10–13 também aprovados (Pix/Mastercard). Não havia débito nessa amostra; isso não demonstra indisponibilidade nem aprovação. Nenhum registro foi atualizado, nenhuma fila foi publicada e nenhuma cobrança foi criada por essa consulta.

## Mercado Pago Integration Review

**Scope**: full
**API detected**: Orders API (`/v1/orders`)
**Products detected**: Checkout API online, Pix, boleto, crédito e débito.
**Files analyzed**: `app/Services/MercadoPagoService.php`, `app/Webservices/MercadoPagoClient.php`, `app/Http/Controllers/Api/PagamentoController.php`, requests de pagamento/webhook, `app/Models/PedidoPagamento.php`, hook e serviço JavaScript do Mercado Pago, páginas Checkout/Pagamento, testes de pagamentos, `.gitignore`, configuração Nginx e documentos de homologação/PCI.

### CRITICAL

- Webhooks automáticos ainda sem aceite comprovado: assinaturas divergentes reproduzidas anteriormente com SDK oficial. Manter HMAC obrigatório.
- Responsável declarou que o site ainda não cumpre PCI DSS; avaliação inicial não comprova conformidade.
- Débito configurado e formulário visual aceito, mas aprovação financeira ainda não comprovada.

### WARNINGS

- Compensação de boleto e restante da matriz interativa ainda pendentes; aceite do boleto digital permanece válido.
- Cancelamento/estorno remoto ainda não implementado.
- Checklist retornado pelo MCP contém critérios de Preferências/Payments e critérios específicos de outros países. Sua aplicabilidade a Orders precisa ser confirmada; não adicionar campos incompatíveis apenas para obter pontuação.

### PASS

- Consulta autenticada da Order antes de confirmar pedido, com validação de referência, moeda e valor.
- Chave de idempotência persistente, token temporário cifrado e removido após obter a Order.
- Campos seguros do SDK JavaScript e HMAC do SDK PHP.

### Quality Standards

Checklist oficial consultado para aplicação `880483999981556` em 19/10/2026. A tabela inclui todos os 19 requisitos e 15 boas práticas retornados. Estados descrevem evidência local, sem atribuir nota oficial ou presumir equivalência entre APIs.

#### Required fields

| # | Campo | Descrição | Status | Evidência |
|---|---|---|---|---|
| 1 | email | E-mail do comprador | Implemented | `app/Services/MercadoPagoService.php:177`, payer.email |
| 2 | payer_first_name | Nome | Partial | Mesmo arquivo:182, depende do snapshot |
| 3 | payer_last_name | Sobrenome | Partial | Mesmo arquivo:184, condicional; obrigatório no boleto |
| 4 | item_category_id | Categoria do item | Missing | Mesmo arquivo:59, payload sem items |
| 5 | item_description | Descrição do item | Missing | Mesmo arquivo:59, payload sem items |
| 6 | item_id | Identificador do item | Missing | Mesmo arquivo:59, payload sem items |
| 7 | item_quantity | Quantidade | Missing | Mesmo arquivo:59, payload sem items |
| 8 | item_title | Título | Missing | Mesmo arquivo:59, payload sem items |
| 9 | item_unit_price | Preço unitário | Missing | Mesmo arquivo:59, apenas total e valor da transação |
| 10 | device_id | Dispositivo/SDK v2 | Partial | `resources/js/services/mercadoPago.js:7`, SDK v2; envio efetivo não inspecionado em navegador |
| 11 | issuer_id | Emissor | Partial | `resources/js/hooks/useCartaoMercadoPago.js:49` monta seletor; callback:102 não envia emissor ao backend |
| 12 | webhooks_ipn | Notificações | Partial | `app/Http/Controllers/Api/PagamentoController.php:30`; entrega chega, assinatura automática ainda divergente; sem notification_url no payload Orders |
| 13 | external_reference | Conciliação | Implemented | `app/Services/MercadoPagoService.php:61` |
| 14 | back_end_sdk | SDK backend | Partial | `app/Http/Requests/Api/MercadoPagoWebhookRequest.php:23`, SDK para assinatura; Orders via HTTP Laravel |
| 15 | web_front_end_sdk | SDK frontend | Implemented | `resources/js/services/mercadoPago.js:7` |
| 16 | statement_descriptor | Identificação na fatura | Missing | `app/Services/MercadoPagoService.php:59`, ausente no payload |
| 17 | ssl | Certificado válido | Partial | `.docker/nginx/default.conf:19`, HTTP local; túnel HTTPS registrado, hospedagem final não validada |
| 18 | tls | TLS >= 1.2 | Partial | Mesmo arquivo:19; produção ainda não validada |
| 19 | secure_form | Campos seguros | Implemented | `resources/js/hooks/useCartaoMercadoPago.js:39`, iframe; não equivale a declaração PCI |

#### Best practices

| # | Prática | Descrição | Status | Evidência |
|---|---|---|---|---|
| 1 | address | Endereço | Partial | `app/Services/MercadoPagoService.php:193`, somente boleto |
| 2 | payer_identification | Documento | Partial | Mesmo arquivo:187, condicional no snapshot; obrigatório no boleto |
| 3 | payer_phone | Telefone | Missing | Mesmo arquivo:175, não enviado |
| 4 | payer_identification_mlm | Documento mexicano | Missing | Mesmo arquivo:187, CPF/CNPJ; critério MLM não aplicável ao Brasil |
| 5 | payment_get_or_search_api | Consulta após notificação | Implemented | `app/Http/Controllers/Api/PagamentoController.php:32` e service:134 |
| 6 | chargebacks_api | Disputas | Missing | `app/Webservices/MercadoPagoClient.php:15`, cliente apenas cria/consulta Orders |
| 7 | cancellation_api | Cancelamento remoto | Missing | Mesmo arquivo:15; pendência em TODO.md |
| 8 | refunds_api | Estorno remoto | Missing | Mesmo arquivo:15; pendência em TODO.md |
| 9 | settlement | Relatório de liberações | Missing | Mesmo arquivo:15, não implementado no cliente |
| 10 | release | Relatório de transações | Missing | Mesmo arquivo:15, não implementado no cliente |
| 11 | binary_mode | Resultado binário | Missing | `app/Services/MercadoPagoService.php:59`; fluxo aceita pendência, validar aplicabilidade a Orders |
| 12 | auth_and_capt | Reserva/captura | Missing | Mesmo arquivo:59, processamento automático |
| 13 | logos | Logotipo oficial | Missing | `resources/js/pages/Checkout.jsx:50`, menção textual, sem logotipo nesse fluxo |
| 14 | response_messages | Feedback ao comprador | Implemented | `resources/js/pages/Pagamento.jsx:39` e `resources/js/services/estadoPagamento.js:4` |
| 15 | payer_id/customer_id | Cartão salvo | Missing | `app/Services/MercadoPagoService.php:175`, sem customer_id; cartões salvos ainda fora do fluxo |

### Security checklist (cross-cutting)

| # | Check | Status | Evidence |
|---|---|---|---|
| 1 | Token fora do código | Pass | `config/mercadopago.php:5`; busca sem literais APP_USR-/TEST- em app/config/resources/routes |
| 2 | .env ignorado, exemplo permitido | Pass | `.gitignore:6` |
| 3 | Assinatura HMAC | Pass | `app/Http/Requests/Api/MercadoPagoWebhookRequest.php:23`; não comprova aceite automático |
| 4 | HTTPS back/notification URL | Partial | Orders sem back_url/notification_url; endpoint público HTTPS registrado, sem garantia de hospedagem final |
| 5 | Verificação financeira no servidor | Pass | `app/Services/MercadoPagoService.php:134` |
| 6 | Idempotência | Pass | `app/Webservices/MercadoPagoClient.php:37` |
| 7 | Referência externa | Pass | `app/Services/MercadoPagoService.php:61` |
| 8 | Isolamento de credenciais de teste no deploy | Partial | .env ignorado; pipeline e ambiente de produção não avaliados |
| 9 | Ausência de sandbox_init_point | Pass | Busca em app/config/resources/routes sem ocorrências |

### Recommendations

1. Obter orientação do suporte para assinatura da aplicação vendedora de teste e comprovar webhook automático HTTP 200 sem reduzir validações.
2. Concluir avaliação e adequação PCI com o responsável; formulário já enviado com declaração de não conformidade.
3. Comprovar habilitação/aprovação de débito e completar a matriz interativa restante.
4. Confirmar aplicabilidade dos critérios retornados para Orders antes de mudar payloads.

**Summary**: checklist examinado integralmente, sem nota oficial; 7/9 verificações locais de segurança aprovadas, 2 parciais. `quality_evaluation` não executado: integração exclusivamente Orders, incompatível com a avaliação por payment_id da Payments API.

## Implementation Report

### Verified

- [x] Evidências locais de HMAC, idempotência, autorização e conciliação revisadas.
- [x] Checklist oficial consultado e todos os itens classificados acima.
- [x] Formulário oficial consultado; responsável reconfirmou varejo, uma marca, Brasil, uma conta e os quatro meios configurados.
- [x] Formulário oficial 16400 salvo para aplicação 880483999981556 com `ou_api_integration_model=no`, conforme declaração explícita do responsável. Retorno `Form saved successfully`, `needs_review=false`; não equivale a certificação ou liberação de produção.
- [x] 45 testes de frontend aprovados nesta revisão, com SDK/DOM e respostas controladas.
- [x] 63 testes PHP aprovados, 524 assertions: MercadoPagoTest, MercadoPagoModalidadesTest, PixBoletoTest e MercadoPagoConfiguracaoTest. Banco SQLite em memória e provedor simulado; não comprovam aprovação remota.

### Needs attention

- [ ] Resultado financeiro do débito e compensação do boleto.
- [ ] Matriz interativa restante em navegador real.
- [ ] Aplicabilidade do checklist genérico à API Orders.

### Blockers (must fix before production)

- [ ] Aceitação de webhooks automáticos ainda não comprovada.
- [ ] Adequação PCI ainda não concluída; declaração de não conformidade já enviada.

### Next steps

1. Resolver divergência de assinatura com suporte e validar notificação automática.
2. Concluir adequação PCI e cenários financeiros/interativos pendentes.
3. Reexecutar mp-review após resolver as pendências.

### Resources used

- Local: código, testes e registros de homologação anteriores.
- MCP: `quality_checklist`, 19/10/2026.
- MCP: `form_homologation`, consulta, validação de campos e submissão bem-sucedida em 19/10/2026, formulário 16400.
- Skill: `mp-review` v1.0.0 e orientação de `mp-test-setup`; nenhuma conta criada ou financiada.

**Scores**: qualidade oficial N/A; segurança local 7/9. **Verdict**: Blocked para produção; homologação parcial.
