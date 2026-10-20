# Acompanhamento HMAC — 20/10/2026

## Validação local sem SDK e divergência do simulador — 20/10/2026

Por solicitação do responsável, substituída a validação do SDK por `MercadoPagoAssinaturaService`, usando funções nativas `hash_hmac('sha256', ...)` e `hash_equals`. O segredo é utilizado como texto, sem conversão de codificação. Timestamp vem exatamente de `x-signature`, request-id de `x-request-id`, e ID da query. Pares ausentes são omitidos no algoritmo, mas o receptor continua exigindo ID e request-id. Headers com ts/v1 ausentes, inválidos ou duplicados são rejeitados. Nenhuma cobrança criada nesta etapa.

Encontrada evidência nova nas seis capturas recentes da **aplicação principal 880483999981556**: cinco simulações com a Order `ORDTST01M5DEC4MBR552X778HTQ9VBGN` têm HMAC válido com o **ID original em maiúsculas**, mas inválido com ID em minúsculas. Uma simulação com ID numérico `123456` é válida nos dois controles. Portanto, o segredo atual autentica essas capturas, e a diferença de caixa explica a rejeição dessas simulações específicas. Não extrapolar esse resultado para as notificações automáticas anteriores da aplicação de teste.

Request-ids alfanuméricos verificados: `1a86d0cc-20f7-4e17-b145-6dd6c308c53c`, `2388085a-e1fe-4bba-a339-8b47a2f1f572`, `c3d144cf-98ca-45cd-8f6c-3da5207653f1`, `70708ac4-b082-44ac-9f12-6e65d4d7f3e8`, `5f893ba6-40cb-4fcf-ab71-7d95d16cd72e`. Controle numérico: `a34017ed-f8c8-4a4b-a0fb-1b441bab72bb`. Nas duas capturas com resposta registrada, o receptor anterior retornara 401.

O novo algoritmo verifica primeiro o ID em minúsculas e, como compatibilidade limitada ao formato comprovado do simulador, o ID original quando diferente. Ambos exigem HMAC válido com o mesmo segredo, request-id e ts; não são ignorados campos presentes nem aceitos hashes incorretos. As seis capturas passaram na nova validação local. Não foi alterado o SDK instalado; a aplicação deixou de chamá-lo para assinatura.

Replay pelo kernel HTTP Laravel local da primeira captura, sem recalcular ou substituir a assinatura: **HTTP 200 nas duas chamadas**, **um único evento persistido**, evento **2** posteriormente `concluido` na fila, com `processado_em=2026-10-20 20:51:24 UTC`. Pedido **23** permaneceu `pago`. Esse teste comprova autenticação da captura, recebimento durável, deduplicação e conciliação pelo worker, mas não mede latência do túnel nem comprova nova entrega automática pública do provedor. Capturas e scripts temporários removidos após a análise.

Testes: primeira execução do algoritmo estrito, `MercadoPagoAssinaturaTest|MercadoPagoTest|PixBoletoTest`, **53 aprovados, 252 assertions**. Após acrescentar compatibilidade comprovada com o simulador, reexecução direcionada final dos vetores unitários e endpoints de assinatura válida/inválida: **23 aprovados, 37 assertions**. Vetores fixos foram calculados independentemente por HMACSHA256 do .NET, incluindo ID original/minúsculo e omissão dos identificadores. Postman e documentação operacional atualizados; JSON e diff verificados.

## Retomada com MCP autenticado — 20/10/2026

Após o responsável confirmar conexão ativa, `application_list` listou a principal **880483999981556**. A retomada de `notifications_history` para essa aplicação continuou retornando `No Notifications Found`; para a aplicação automática **1044824833239556**, continuou retornando `OAuth ownership validation failed`. Autenticação funcional da conta principal não removeu a restrição de propriedade da aplicação automática.

`get_credentials` da principal retornou seções de produção e teste e referência à aplicação automática, sem fornecer segredo de webhook. Credenciais não foram exibidas, copiadas para o projeto nem alteradas. A ferramenta não permite confirmar a associação do segredo do painel ao emissor. Busca documental oficial via MCP também não esclareceu essa associação. Uma nova captura local do pedido 23, às 17:21:40 de Brasília, request-id `8e88b26e-6fbb-4a58-8895-d5ed2e193388`, não tinha resposta HTTP registrada no túnel na consulta. Isso não comprova aceite nem causa de falha.

Nenhuma nova cobrança criada nesta retomada, nenhuma configuração de webhook ou PKCE alterada. Permanece necessário obter do suporte o vínculo entre a chave da principal e a assinatura da aplicação automática de teste, além de acesso ao histórico correto. A conexão do MCP no VS Code e a ferramenta disponível na conversa não foram demonstradas como sendo a mesma sessão OAuth.

## Reteste de cartão e esclarecimento de PKCE — 20/10/2026

Após o responsável relatar uma alteração de configuração no cadastro e solicitar outro teste, criado **um único Mastercard de homologação de R$ 50,00**, cartão oficial fictício e titular `APRO`. Referência `webhook-homolog-ebf8f2e4-cdb4-4966-a74b-20ff4d7101c7`, pedido **23**, Order **ORDTST01M5DEC4MBR552X778HTQ9VBGN**. Credenciais e token não foram exibidos; tokenização em memória e persistência temporária cifrada pelo fluxo normal, com descarte após obter a Order. Snapshot fiscal fictício permaneceu cancelado. Configuração sem cache, segredo carregado igual ao `.env`.

PostgreSQL confirmou pedido `pago`, Order `processed` e processamento `concluido` pelo worker. Captura automática às **16:59:54 de Brasília**, request-id `12a77118-1718-4e57-97f9-30d6c38bd372`, sem resposta HTTP registrada no túnel durante a verificação (`status_code=0`). Nenhum evento autenticado para essa Order na consulta ao banco; HTTP 200 automático ainda não comprovado.

O responsável esclareceu depois que a opção marcada menciona **PKCE e OAuth**. Isso não identifica uma configuração de 3DS: PKCE protege a troca do código de autorização por Access Token e, quando habilitado, exige parâmetros adicionais nas requisições OAuth. A cobrança da loja usa diretamente o Access Token de teste configurado, sem executar esse fluxo. Não foi demonstrado vínculo entre essa opção e o cálculo HMAC de notificações. O momento exato da alteração no painel em relação ao pagamento não foi verificado. Nenhuma configuração do painel foi alterada pelo agente.

Fonte: [Obter Access Token — configurar PKCE](https://www.mercadopago.com.br/developers/pt/docs/security/oauth/creation). Não modificar o PKCE do OAuth/BFF interno da loja com base nessa opção do provedor.

Verificação direta da captura do pedido 23 com SDK, segredo atual e ID em minúsculas: **SignatureMismatch**. Assim, a assinatura capturada continua divergente. Script de tokenização, captura privada e script de verificação foram removidos após o diagnóstico; registros de domínio preservados. Nenhuma segunda cobrança criada.

## Recebimento durável e conciliação em fila — 20/10/2026

Aplicado o fluxo de confirmação rápida: HMAC obrigatório, registro durável do evento autenticado, publicação após commit na fila Redis `pagamentos` e HTTP 200 sem consulta externa. `ProcessarMercadoPagoEvento` consulta a Order autenticada e marca o evento como concluído somente após conciliar. Falhas de publicação preservam pendências, recuperadas por `webhooks:retomar` no scheduler. Deduplicação dos campos assinados, bloqueio de processamento concorrente e retentativas preservam a idempotência. Sem payload bruto, assinatura ou segredo no registro/job. Migration aplicada no PostgreSQL local e Horizon recarregado. Detalhes operacionais em [MERCADO-PAGO.md](MERCADO-PAGO.md).

Validação: na suíte `MercadoPagoTest|PixBoletoTest`, 33 cenários passaram inicialmente; dois cenários novos exigiram corrigir mocks de falha/recuperação e o prazo do bloqueio único. Reexecução direcionada final de recuperação do Redis, retentativa após falha do provedor e idempotência: **3 testes aprovados, 19 assertions**. Assim, os 35 cenários distintos foram aprovados entre as execuções. Testes usam SQLite em memória e provedor simulado. JSON do Postman e `git diff --check` validados. Não foi realizada nova execução do teste de integração que cria múltiplas cobranças.

Reteste remoto expressamente autorizado: um único Pix de homologação de **R$ 50,00**, `APRO Teste`, referência `webhook-homolog-dd2a2b5f-81bb-45b4-ac99-c8a04a389671`, pedido **22**, Order **ORDTST01M5DB1GQZVNGVVZVSBF89B5DG**. Configuração sem cache e segredo carregado igual ao `.env`. PostgreSQL confirmou pedido `pago`, Order `processed`, processamento `concluido` pelo worker de pagamentos.

Webhook automático de **16:01:40 de Brasília**, request-id `a24a5f41-382b-44dc-a5ae-63d945903070`: **HTTP 401**. Outra captura, às 16:02:04, request-id `db1934ee-a3f4-4cff-9ad1-d9c69cc51c6d`, ainda sem resposta HTTP registrada na consulta. Nenhum evento persistido para essa Order na verificação. O novo fluxo não relaxou autenticação: a rejeição continua anterior ao registro e à fila. HTTP 200 para notificações automáticas continua não comprovado; a mudança de processamento não resolveu a divergência de assinatura.

## Cobrança nova após correção de minúsculas — 20/10/2026

Por autorização expressa do responsável, criado um único Pix de homologação de R$ 50,00 com `APRO Teste`, referência `webhook-homolog-5f44ea24-d819-43f8-a144-1e54e8345fb9`. Pedido **21**, Order **ORDTST01M5D9VSWZFZ3S1AM6PXFQY6RQ**. Configuração sem cache e segredo carregado igual ao `.env`. O worker conciliou para `processed`, processamento `concluido` e pedido `pago`, confirmado no PostgreSQL às 18:42:04 UTC (15:42:04 de Brasília).

O webhook automático continua sem aceite: captura de **15:42:46 de Brasília** com HTTP **401**, request-id `cd010399-e3cd-4319-84fe-e2ff5f00b046`. As duas primeiras capturas, request-ids `dadd868b-90d0-4b96-a9b1-bd91c55502aa` e `877423da-177b-46ef-83a0-b1e8283db59a`, foram verificadas diretamente com SDK e ID em minúsculas: ambas `SignatureMismatch`; não tinham resposta HTTP registrada no túnel. Não interpretar status zero como aceite.

Confirmada a presença de `data.id` e `x-request-id` nas duas capturas verificadas. A regra documental de omitir pares ausentes do manifest não se aplica a elas. O SDK já omite pares ausentes; o receptor exige os dois identificadores antes da validação. Timestamp continua extraído de `x-signature`. Nenhuma validação foi relaxada. Aprovação e conciliação pelo worker não comprovam sucesso do webhook. Scripts e capturas privados temporários removidos após diagnóstico.

## Correção da normalização do manifest — 20/10/2026

Após o responsável destacar a regra oficial, a leitura do SDK PHP instalado mostrou uma inconsistência: o comentário informa que `dataId` é convertido para minúsculas, mas `buildManifest()` concatena o ID sem essa conversão. O receptor também passava o ID original. Corrigido `MercadoPagoWebhookRequest` para passar `strtolower($id)` somente à validação; a consulta autenticada mantém o ID original. `x-request-id` continua vindo do header e o SDK extrai `ts` de `x-signature`, sem gerar outro timestamp.

Os controles sintéticos anteriores assinavam o ID em maiúsculas; seu sucesso não comprovava aderência à regra documental. Os testes e o roteiro de integração foram corrigidos para assinar em minúsculas, com regressão para rejeitar manifest em maiúsculas e preservar o ID original na consulta. Postman atualizado. Nenhuma dependência foi alterada.

Reverificadas cinco capturas existentes do pedido 20 usando o SDK com ID em minúsculas e o segredo atual: todas continuam com `SignatureMismatch` (request-ids `96658ac0-f193-43fa-89d6-2bee447e1901`, `46d5c494-e7b4-4686-abe4-dd5196d34907`, `3d18e3f7-45b1-4049-bdfa-36b386031376`, `2016b299-d7db-4710-b2c5-d10dc34fad17`, `a5354d22-f7fd-4f9e-ba85-c92a9ffcfbf8`). A correção local não resolve essas capturas. Nenhuma nova cobrança foi criada e não foi comprovado HTTP 200 automático. Capturas e script temporários removidos após a verificação.

Fonte da regra: [validar a origem da notificação em Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/notifications).

Validação da correção: `MercadoPagoTest|PixBoletoTest`, **33 testes aprovados, 213 assertions**, com SQLite em memória e provedor simulado. Cobertura confirma assinatura válida com manifest em minúsculas, rejeição de manifest em maiúsculas e consulta pelo ID original. JSON da coleção Postman validado. O roteiro de integração remota foi atualizado, mas não executado para evitar novas cobranças.

Pendência aberta, número WCS ainda não informado. Nenhuma mensagem foi enviada ao suporte nesta retomada: o MCP disponível não oferece consulta ou atualização de chamados. Nenhuma cobrança foi criada, nenhum segredo foi alterado e HMAC continua obrigatório.

## Evidências da retomada

- `notifications_history(application_id=1044824833239556)` retornou `OAuth ownership validation failed`: aplicação não pertence ao proprietário da conexão OAuth. É uma restrição de propriedade, não evidência de falha de autenticação da conexão inteira.
- A mesma consulta para a principal `880483999981556` retornou `No Notifications Found`. Não permite concluir que não há entregas para a aplicação vendedora.
- Capturas locais do ngrok contêm novas notificações com `application_id=1044824833239556` para Orders antigas. A captura de 20/10/2026 às **12:16:11 de Brasília** (15:16:11 UTC) refere-se a `ORDTST01M56VDW0W9181HWRA0G3WMGN6`, request-id `fdf3319c-8f78-41e9-87a5-3545b1e2a956`. Não há resposta HTTP registrada nessa captura (`status_code=0`); zero não é um status HTTP nem comprova timeout ou aceite.
- Duas capturas de 19/10/2026, às 18:22:46 e 18:22:57 de Brasília, referem-se à mesma Order `ORDTST01M5B0Q6W6ZPBMC04Y0K9N152K`, request-ids `9655fc2b-bffc-4b99-8e9f-117737e036c0` e `457064f6-c322-4ff0-b7e1-880de36c6294`, ambas com HTTP **401**. Os envios repetidos estão observados; não foi possível atribuí-los a retentativa automática, pois o header `X-Retry` não consta nessas capturas.
- HTTP 200 de controles anteriores não comprova aceite de notificação automática. Registro de evento e conciliação automática continuam sem comprovação nesta retomada.
- A documentação oficial consultada via MCP mantém o uso de `WebhookSignatureValidator::validate(x-signature, x-request-id, data_id, secret)`, como implementado no receptor. Não foi identificada nessa consulta uma mudança de contrato que justifique alterar a validação.

Fonte: [notificações de Orders](https://www.mercadopago.com/developers/pt/docs/checkout-api-orders/notifications). Os testes locais e diagnósticos anteriores com o SDK oficial estão em [MERCADO-PAGO-HOMOLOGACAO.md](MERCADO-PAGO-HOMOLOGACAO.md).

## Validação local

Horizon retornou `Horizon is running`. Executado `docker compose exec -T php php artisan test --filter='MercadoPagoTest|PixBoletoTest'`: **32 testes aprovados, 211 assertions**. Banco SQLite em memória e HTTP simulado, sem cobranças reais. Cobertura inclui HTTP 200 para assinatura válida, HTTP 401 sem consulta ao provedor para assinatura inválida, repetição idempotente, consulta autenticada do recurso assinado e conciliação Pix/boleto. Isso não comprova retentativas do emissor nem resolve a assinatura das notificações automáticas.

## Texto pronto para o chamado existente

Solicitamos continuidade do diagnóstico de assinatura HMAC dos webhooks Orders da aplicação vendedora de teste **1044824833239556**, vinculada à principal **880483999981556**. Favor informar o número WCS e confirmar qual aplicação/configuração de webhook fornece o segredo efetivamente usado pelo emissor, sem transmitir o segredo no chamado.

A divergência já foi reproduzida com o SDK PHP oficial 3.16.0; o mesmo validador aceita controle sintético assinado localmente. Exemplo anterior: Order **ORDTST01M5AE4AWG3F8MTP7B7MWSR1VT**, request-id **a88fbd68-a09d-49e2-9ed0-09d9dee6afb1**, rejeitada com SignatureMismatch. Favor verificar o manifest efetivamente assinado, a normalização do ID e a associação da chave no emissor.

Nova referência para rastreamento: request-id **fdf3319c-8f78-41e9-87a5-3545b1e2a956**, **20/10/2026 15:16:11 UTC**, Order **ORDTST01M56VDW0W9181HWRA0G3WMGN6**. O túnel capturou o envio sem resposta HTTP registrada. Favor confirmar o resultado de entrega no provedor.

O MCP bloqueia o histórico da vendedora por propriedade OAuth e retorna histórico vazio para a principal. Solicitamos orientação para consultar o histórico correto e reenviar uma notificação existente, sem criar nova cobrança. Após esclarecer o segredo/manifest, precisamos comprovar HTTP 200, retentativas, evento persistido e conciliação idempotente por consulta autenticada da Order. A validação HMAC permanecerá obrigatória.

## Novo teste autorizado após redefinição — 20/10/2026

Após o responsável informar que redefiniu a assinatura e autorizar expressamente uma nova cobrança, executado **um Pix de teste de R$ 50,00**, cenário `APRO Teste`, pelo roteiro `scripts/homologar-webhook.php`. Referência `webhook-homolog-184b5318-d222-4c06-84b7-891ba23781df`, pedido **20**, registro de pagamento **16**, Order **ORDTST01M5D7RE4XB6TC5CR4SQCTYE0Q**. A autorização foi específica para este reteste; não foram criadas outras cobranças nesta execução.

A primeira leitura encontrou diferença entre segredo carregado e arquivo; uma nova leitura confirmou igualdade. PHP e Horizon foram reiniciados antes da criação. Na criação e na validação final: ambiente local, configuração sem cache, segredo carregado igual ao `.env` e com 64 caracteres; valor não registrado.

O worker criou a Order, inicialmente `action_required`, e conciliou para `processed`, processamento `concluido`, pedido `pago`, confirmado no PostgreSQL. Snapshot fiscal fictício permaneceu em `cancelado`. Esse resultado comprova aprovação do cenário e conciliação pelo worker, não aceite do webhook.

Capturas automáticas da aplicação vendedora **1044824833239556**:

| Horário de Brasília | Request-id | HTTP capturado | SDK oficial com segredo atual |
| --- | --- | --- | --- |
| 20/10/2026 15:04:16 | `96658ac0-f193-43fa-89d6-2bee447e1901` | 401 | SignatureMismatch |
| 20/10/2026 15:04:33 | `46d5c494-e7b4-4686-abe4-dd5196d34907` | Sem resposta registrada (0) | SignatureMismatch |
| 20/10/2026 15:05:29 | `3d18e3f7-45b1-4049-bdfa-36b386031376` | Sem resposta registrada (0) | SignatureMismatch |

As três assinaturas foram verificadas diretamente com o SDK instalado e o segredo atual, sem alterar o receptor. Não há HTTP 200 automático comprovado após a redefinição. Envios repetidos foram observados, mas a atribuição a retentativas do emissor continua sem confirmação. Um controle sem assinatura foi rejeitado com HTTP 401 pelo receptor, conforme captura do ngrok; o status visto pelo cliente externo não foi usado como evidência de aceite da aplicação. A divergência permanece e estes novos request-ids podem ser associados ao chamado WCS. Capturas privadas e script temporário removidos após o diagnóstico; os registros de domínio foram preservados.
