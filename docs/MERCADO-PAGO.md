# Mercado Pago — Checkout Transparente via Orders

O backend cria cobranças para pedidos já persistidos. O valor vem dos itens do pedido, nunca do navegador. As chamadas de Orders ficam em `app/Webservices/MercadoPagoClient.php`, usando o cliente HTTP do Laravel. `MercadoPagoService` mantém criação local, filas e conciliação dos pedidos. A assinatura dos webhooks é validada localmente por MercadoPagoAssinaturaService, com hash_hmac e hash_equals, sem chamar o SDK.

## Configuração

Preencha somente no `.env` os campos já adicionados, sem aspas quando não forem necessárias:

```dotenv
MERCADO_PAGO_ACCESS_TOKEN=
MERCADO_PAGO_PUBLIC_KEY=
MERCADO_PAGO_WEBHOOK_SECRET=
```

Use credenciais da aplicação Mercado Pago e compradores de teste compatíveis. Os exemplos versionados ficam vazios; nenhum segredo deve ser incluído em commits ou no Postman. Após alterar o ambiente, execute `docker compose exec php php artisan config:clear`. Aplique a migration com `docker compose exec php php artisan migrate`.

No painel da aplicação Mercado Pago, configure o evento **Order (Mercado Pago)** para `https://SEU-DOMINIO/api/webhooks/mercado-pago`. Copie o segredo gerado para o `.env`. A URL pública HTTPS é configurada no painel; não é enviada no corpo da Order. Localmente pode ser usado o túnel já existente no projeto.

## API

### Diagnóstico local

Execute `docker compose exec php php artisan mercadopago:verificar` para verificar as três variáveis sem imprimir seus valores, acessar a conta ou criar cobranças. O comando retorna erro quando alguma variável está ausente. A Public Key é publicada somente para o Cliente autenticado por GET /api/pagamentos/configuracao, via BFF no navegador; o Access Token e o segredo do webhook permanecem privados.

Obtenha as credenciais na aplicação do [painel Mercado Pago](https://www.mercadopago.com.br/developers/panel/app). Para homologação, use as credenciais e contas de teste indicadas para Orders. Não compartilhe Access Token ou segredo do webhook no chat ou no Postman. O segredo é fornecido pelo painel ao configurar as notificações; não gere um valor aleatório local em seu lugar.

O diagnóstico verifica preenchimento, não validade das credenciais ou entrega das notificações. Se APP_URL for local, ele orienta cadastrar uma URL pública HTTPS; isso não impede testes locais da API. A Public Key é carregada em tempo de execução, com no-store, sem precisar recompilar o frontend após mudar a chave. Antes de produção, conclua a homologação e a revisão da integração (`mp-review`).

Com conta pronta, perfil Cliente e escopo `orders:write`:

- `POST /api/pedidos/{pedido}/pagamento`: aceita uma única solicitação por pedido com HTTP 202, sem aguardar o provedor. A criação ocorre na fila Redis `pagamentos`. Pix: `{"meio":"pix"}`. Boleto: `{"meio":"boleto"}` (sem token ou bandeira; usa identificação e endereço do snapshot fiscal do pedido). Cartão: `{"meio":"credit_card","payment_method_id":"master","token":"TOKEN_GERADO_PELO_PROVEDOR","parcelas":1}`. Débito usa `debit_card` e uma parcela. O meio deve corresponder à forma cadastrada no pedido (`pix`, `boleto`, `cartao_credito`, `cartao_debito`).
- `GET /api/pedidos/{pedido}/pagamento`: consulta somente o estado local da cobrança; worker e scheduler conciliam o pedido em segundo plano. Ambos os endpoints verificam propriedade. Estão também na lista explícita do BFF, em `/bff/api/pedidos/{pedido}/pagamento`, preservando cookie/CSRF e os middlewares da API.
- `POST /api/webhooks/mercado-pago?data.id=ORD...`: exige `x-signature` e `x-request-id`, valida HMAC SHA-256 e consulta o ID assinado. O corpo não confirma pagamentos. O manifest aceita ID em minúsculas ou original, ambos com HMAC válido; a consulta em fila preserva o ID original.

As respostas seguem o envelope existente. A cobrança retorna `processamento`, `order_id` (inicialmente nulo), `status` remoto e `instrucoes` com os campos Pix disponíveis (`qr_code`, `qr_code_base64`, `ticket_url`). Chaves internas e hashes não são serializados. As instruções são cifradas no banco; o snapshot de envio e o token efêmero do cartão ficam temporariamente cifrados em `dados_envio`, ocultos nas respostas e fora do payload do job. São apagados ao obter o Order ID ou esgotar as tentativas de envio. PAN/CVV nunca são recebidos ou armazenados pela loja.

Somente Orders com status `processed/accredited`, país/moeda compatíveis com BRL, referência do pedido, total e total pago correspondentes confirmam o pagamento. O contrato atual de Orders identifica o Brasil por `country_code=BRA`; quando `currency` ou `currency_id` forem informados, devem ser BRL e não podem conflitar com o país. Respostas sem país nem moeda são rejeitadas. O observer existente libera o processamento fiscal após commit. Eventos repetidos consultam o estado atual do provedor, sem repetir a transição de pagamento. Respostas não exitosas permitem retentativa pelo provedor.

A chave de idempotência é persistida antes da chamada externa e reutilizada após timeout. O worker repete o mesmo snapshot com a mesma chave; se houver falha ao receber a aceitação HTTP, repita o POST com os mesmos dados; a API rejeita trocar os dados de uma tentativa existente. Há uma cobrança por pedido nesta etapa. Não gere uma segunda tentativa com outro token após resultado incerto. Enquanto existir tentativa, o cancelamento local retorna 409 para não liberar estoque de cobrança pagável. Cancelamento/estorno remoto e novas tentativas após rejeição precisam de fluxo operacional próprio; não há liberação automática de estoque por eventos financeiros.

## Validação e limites

Boleto usa Orders com `payment_method.id=boleto` e `type=ticket`. O servidor obtém nome/sobrenome ou razão social, CPF/CNPJ e endereço do snapshot fiscal confirmado no checkout, além do e-mail da conta. Snapshot incompleto retorna 422 antes de persistir ou enfileirar cobrança. Não são necessários token de cartão nem SDK adicional. Criação continua assíncrona (202), com a mesma chave em retentativas.

O checkout oferece **Gerar boleto**, linha digitável copiável e link HTTPS para abrir/imprimir o documento retornado pelo provedor. Consulte o vencimento nesse documento; o fluxo usa o prazo padrão do provedor, sem configurar expiração de order manual. `instrucoes` inclui `digitable_line`, `barcode_content` e `ticket_url`, cifrados no banco. Pedido permanece aguardando pagamento até consulta autenticada confirmar o valor compensado. Webhooks e conciliação usam o fluxo existente. Não há cancelamento local enquanto existir cobrança, inclusive boleto vencido: cancelamento remoto continua pendente.

Contrato oficial: [Boleto na Orders API](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/websites/boleto). Na homologação remota, confirme disponibilidade na conta e emissão com comprador de teste; os testes locais simulam os retornos, sem gerar boleto real.

### Testes de modalidades pelo terminal

Recusas HTTP 402 na criação de Orders podem conter a cobrança em `data`. O backend preserva seu identificador e confirma o resultado por GET antes de concluir a tentativa. Erros sem Order válida continuam sendo falhas técnicas; uma consulta que falhe após obter o ID retoma somente o GET, sem recriar a cobrança.

Para executar a API e o job contra o provedor real de teste, fora das suites padrão: `docker compose exec -T -e MP_HOMOLOGACAO_TESTE_CONFIRMADO=1 php php artisan test tests/Integration/MercadoPagoHomologacaoTest.php`. Use somente depois de confirmar que as credenciais configuradas são de teste. A execução usa SQLite em memória, dados fictícios, filas interceptadas e chamadas reais ao Mercado Pago. Não testa o navegador, o Horizon nem entrega automática de webhook. O webhook local é assinado e consulta a Order real. Os resultados exibem somente IDs, estados e nomes dos campos de instruções; não incluem tokens, códigos Pix ou linhas digitáveis. Débito indisponível é reportado como não homologado, sem considerar sua recusa uma aprovação.

Execute `docker compose exec -T php php artisan test --filter='PixBoletoTest|MercadoPagoModalidadesTest|ProcessarPedidoPagamentoTest|MercadoPagoTest|MercadoPagoConfiguracaoTest'`.

`MercadoPagoModalidadesTest` cobre fixtures de crédito Visa/Mastercard/Elo/Amex e débito Visa/Mastercard, aceitação HTTP 202 sem chamada externa, execução do worker, total dos itens, idempotência, proteção do token, recusa, pendência, cancelamento/expiração/estorno informados pelo provedor, timeout e payloads inválidos. `ProcessarPedidoPagamentoTest` isola a decisão de reagendar a conciliação e a propagação de falhas para retentativa. São testes PHPUnit com HTTP simulado e bloqueio de chamadas inesperadas, sem navegador e sem cobranças reais.

Os identificadores de bandeiras nesses testes são fixtures, não uma confirmação dos meios habilitados na conta. `PixBoletoTest` cobre QR Code, linha digitável, link do boleto, snapshot cifrado, confirmação por webhook e rejeição de boleto com cadastro incompleto. A cobertura não equivale à homologação remota dos meios de pagamento.

Os testes usam `Http::fake`, sem cobranças reais. A homologação precisa verificar credenciais, recursos e meios habilitados na conta, parcelas, tarifas, Pix e cartões de teste. Crédito/débito dependem da disponibilidade da conta.

O checkout React integra a API e o MercadoPago.js v2, carregado do domínio oficial. PAN/CVV e validade ficam nos iframes do provedor e nunca passam pela API da loja. A Public Key vem da configuração de ambiente por endpoint autenticado e sem cache; Access Token e segredo do webhook permanecem exclusivamente no servidor. Não foram adicionadas dependências ou substituído o cliente HTTP Laravel existente.

Referências oficiais:

- [Modelo de Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-model)
- [Pix](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/websites/pix)
- [Notificações de Orders](https://www.mercadopago.com.br/developers/en/docs/checkout-api-orders/notifications)
- [Validador oficial de assinatura](https://github.com/mercadopago/sdk-php/blob/master/src/MercadoPago/Webhook/WebhookSignatureValidator.php)

## Checkout e tokenização React

O seletor de forma de pagamento para novas compras exibe Pix, boleto e cartão de crédito. Débito fica oculto até confirmar a habilitação da conta, após recusa no teste remoto. O suporte existente na API e o acompanhamento de pedidos anteriores permanecem disponíveis; ocultar a opção no React não é uma regra de autorização da API.

O botão **Finalizar compra** (`resources/js/components/BarraSuperior.jsx`) abre `/checkout`, que valida os dados fiscais e persiste o pedido. **Confirmar pedido** leva à nova página `/checkout/pagamento/{id}`. A página sempre obtém pedido, total e forma de pagamento do servidor, preservando autorização por proprietário. **Continuar pagamento**, em Meus pedidos, permite retomar a página após sair ou recarregar.

- Pix: **Gerar Pix** aceita a solicitação e o worker cria a cobrança; são exibidos QR Code e código copia e cola retornados pelo provedor.
- Cartão: o hook `useCartaoMercadoPago` monta e desmonta CardForm com `iframe: true`, usa campos seguros para número, validade e CVV e coleta nome/documento do titular para tokenização. O e-mail vem da conta autenticada. O emissor só aparece quando exigido pelos metadados do SDK. Crédito e débito são à vista; a API exige `parcelas=1`.
- Somente `meio`, `payment_method_id`, `token` e `parcelas` são enviados pela loja na criação de uma cobrança com cartão. A identificação fiscal do comprador vem do snapshot cifrado do pedido, quando disponível, e é enviada pelo servidor ao Mercado Pago. O valor é sempre recalculado a partir dos itens persistidos.
- A tela indica carregamento, erro, pendência e confirmação. Confirmação visual usa o status local do pedido após reconciliação, nunca uma query string ou retorno de tokenização. Enquanto pendente, consulta a cada dez segundos, até sessenta consultas visíveis, com atualização manual disponível.
- O navegador conserva o token apenas em memória até a aceitação HTTP. O servidor salva um snapshot cifrado para o worker e retoma a tentativa mesmo que o cliente saia da página. Após aceitar o cartão, a interface navega para o pedido. Pix aguarda as instruções sem overlay; os links de navegação continuam disponíveis. Não é necessário gerar outro token ou reenviar o cartão para continuar o processamento.
- O Order ID retornado na criação é persistido antes da consulta de confirmação. Se essa consulta falhar, o worker retoma o GET no provedor sem reenviar o POST; o GET público apenas lê o resultado salvo.

O endpoint `GET /api/pagamentos/configuracao` exige perfil Cliente e `orders:write`; retorna somente `public_key` e `parcelas`, com `Cache-Control: no-store, private`. Public Key ou Access Token ausente retorna 503. O frontend usa `/bff/api/pagamentos/configuracao` com `cache: no-store`. O BFF preserva cookie, CSRF, escopos e propriedade em toda chamada de pagamento.

Não há salvamento de cartões neste fluxo: tokens são temporários e de uso único. Parcelamento, recorrência, split, habilitação de 3DS, estornos remotos e novas tentativas após rejeição não foram adicionados. A disponibilidade real de crédito/débito e os cenários de autenticação precisam ser homologados com a aplicação Mercado Pago antes de produção.

## Verificação desta entrega

Testes automatizados usam `Http::fake`, incluindo cartão tokenizado, Pix via BFF/OAuth, configuração pública sem credenciais privadas, recusa de PAN/CVV/valor do navegador e recuperação após falha de consulta. O build verifica os componentes React; ele não substitui um teste com o SDK externo em navegador.

O diagnóstico local encontrou as três credenciais ausentes. Não foram feitas cobranças, criadas contas ou importadas credenciais. Preencha o `.env`, execute `docker compose exec php php artisan config:clear` e valide com comprador de teste compatível. Webhook precisa de URL pública HTTPS e segredo fornecido pelo painel. Homologação e `mp-review` continuam pendentes antes de produção.

O validador estático `validate-checkout-screen.mjs` da skill foi executado, mas não reconhece o formulário dividido em página, componente, hook e cliente HTTP do React/Laravel; ele analisa um único arquivo e procura chamadas `fetch` e configuração no mesmo texto. Por isso, sua validação não passou. Não foi alterada a arquitetura para contornar o verificador. A montagem/interação dos iframes e os cenários reais de cartão/Pix ainda precisam de validação em navegador com as credenciais preenchidas.

Fontes adicionais: [CardForm e lifecycle do SDK oficial](https://github.com/mercadopago/sdk-js/blob/main/docs/card-form.md), [cartões via Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/websites/cards).

Contrato de país/moeda conferido na [referência oficial de consulta da Order](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api/get-order/get).

## Fila de pagamentos

Quando a confirmação verificada muda o pedido de aguardando pagamento para pago, `PedidoService` enfileira `PagamentoConfirmadoNotification` ao usuário Cliente, independentemente da modalidade ou da origem da conciliação (worker/webhook). A transição é protegida pelo bloqueio do pedido; confirmações repetidas não enfileiram outra mensagem. A notificação usa Redis/`emails`, payload cifrado, envio após commit e três tentativas com backoff de 10/30/60 segundos. O e-mail contém número e link do pedido, sem instruções de cobrança ou credenciais. Contas e perfis excluídos são descartados antes do envio. Horizon deve estar ativo; a aprovação não garante entrega imediata na caixa de entrada. O acompanhamento na tela é automático, sem botão de atualização manual.

Aplique a migration `2026_10_18_120000_add_processamento_to_pedido_pagamentos` e reinicie o Horizon para carregar o supervisor `pagamentos`. Ele usa conexão Redis, timeout de 45 segundos por job, oito tentativas com backoff de 10/30/60/120 segundos, exclusão mútua por pagamento e somente o identificador local no payload. A publicação acontece após commit. Se o Redis falhar, a solicitação continua no PostgreSQL e `pagamentos:retomar`, executado a cada minuto, recupera registros parados há dois minutos. O scheduler também retoma a conciliação de Orders pendentes, sem depender de navegador aberto ou webhook.

`processamento` representa o estado local: `pendente`, `processando`, `aguardando` retorno remoto, `concluido` ou `verificacao_necessaria`. O campo `status` continua representando a Order. Sem Order ID e após esgotar tentativas ou ultrapassar dez minutos desde a solicitação, o resultado é incerto: requer verificação operacional e não é declarado como rejeição. O token efêmero é apagado e uma nova cobrança não é autorizada. Com Order ID, a conciliação continua pelo scheduler. Tentativas legadas sem snapshot nem Order ID também exigem verificação. O webhook assinado continua consultando o recurso do provedor e pode recuperar uma Order criada antes de um timeout.

Os endpoints de pedido incluem apenas o resumo `pagamento` (`id`, `pedido_id`, `status`, `processamento`). A página de detalhes atualiza esse estado em segundo plano a cada dez segundos enquanto o pagamento estiver em andamento. Nenhuma confirmação do navegador altera o status financeiro do pedido. A emissão fiscal continua sendo liberada somente pela confirmação validada do provedor.

O receptor usa MercadoPagoAssinaturaService para validar HMAC-SHA256 localmente. Primeiro verifica o manifest com ID da query em minúsculas; como compatibilidade comprovada nas capturas do simulador, também verifica o ID original quando diferente. Ambos exigem HMAC válido com o mesmo segredo, request-id e ts. A consulta preserva o ID original. O request-id vem de x-request-id e ts vem de x-signature, sem conversão de unidade. A comparação usa hash_equals; headers sem ts/v1 válidos ou com esses campos duplicados são rejeitados. Assinatura inválida ou ausente retorna 401. ID alfanumérico e x-request-id permanecem obrigatórios; falhas não persistem eventos nem consultam o provedor.

Webhooks autenticados são registrados em `mercado_pago_eventos`, com exclusão lógica e retenção permanente. Persistem somente Order ID, request-id, digest de deduplicação, status e datas; corpo e assinatura não são armazenados. HTTP 200 (`{"recebido":true}`) confirma o registro durável, sem aguardar consulta externa. `ProcessarMercadoPagoEvento`, na conexão Redis/fila `pagamentos`, consulta e concilia a Order, marcando o evento como concluído após sucesso. O payload do job contém somente o ID local. Retentativas usam oito tentativas e backoff de 10/30/60/120 segundos; falha definitiva marca `falhou` e fica no Horizon para diagnóstico. Reexecutar o job no Horizon retoma o mesmo evento, após análise da causa.

Aplicar a migration `2026_10_20_200000_create_mercado_pago_eventos_table` antes de ativar o receptor e reiniciar Horizon. Se a publicação falhar, o evento fica pendente; `webhooks:retomar`, agendado a cada minuto, republica pendências com pelo menos dois minutos. Envios repetidos com os mesmos campos assinados compartilham um registro. Confirmações e atualizações repetidas continuam protegidas pela conciliação idempotente do pedido. Isso não altera a chave de assinatura nem resolve divergências HMAC do emissor.
