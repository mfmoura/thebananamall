# Mercado Pago — Checkout Transparente via Orders

O backend cria cobranças para pedidos já persistidos. O valor vem dos itens do pedido, nunca do navegador. A implementação usa o cliente HTTP do Laravel, sem SDK adicional.

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

- `POST /api/pedidos/{pedido}/pagamento`: aceita uma única solicitação por pedido com HTTP 202, sem aguardar o provedor. A criação ocorre na fila Redis `pagamentos`. Pix: `{"meio":"pix"}`. Cartão: `{"meio":"credit_card","payment_method_id":"master","token":"TOKEN_GERADO_PELO_PROVEDOR","parcelas":1}`. Débito usa `debit_card` e uma parcela. O meio deve corresponder à forma cadastrada no pedido (`pix`, `cartao_credito`, `cartao_debito`).
- `GET /api/pedidos/{pedido}/pagamento`: consulta somente o estado local da cobrança; worker e scheduler conciliam o pedido em segundo plano. Ambos os endpoints verificam propriedade. Estão também na lista explícita do BFF, em `/bff/api/pedidos/{pedido}/pagamento`, preservando cookie/CSRF e os middlewares da API.
- `POST /api/webhooks/mercado-pago?data.id=ORD...`: exige `x-signature` e `x-request-id`, valida HMAC SHA-256 e consulta o ID assinado. O corpo não confirma pagamentos. A assinatura preserva a caixa do ID, conforme o código atual do SDK oficial.

As respostas seguem o envelope existente. A cobrança retorna `processamento`, `order_id` (inicialmente nulo), `status` remoto e `instrucoes` com os campos Pix disponíveis (`qr_code`, `qr_code_base64`, `ticket_url`). Chaves internas e hashes não são serializados. As instruções são cifradas no banco; o snapshot de envio e o token efêmero do cartão ficam temporariamente cifrados em `dados_envio`, ocultos nas respostas e fora do payload do job. São apagados ao obter o Order ID ou esgotar as tentativas de envio. PAN/CVV nunca são recebidos ou armazenados pela loja.

Somente Orders com status `processed/accredited`, país/moeda compatíveis com BRL, referência do pedido, total e total pago correspondentes confirmam o pagamento. O contrato atual de Orders identifica o Brasil por `country_code=BRA`; quando `currency` ou `currency_id` forem informados, devem ser BRL e não podem conflitar com o país. Respostas sem país nem moeda são rejeitadas. O observer existente libera o processamento fiscal após commit. Eventos repetidos consultam o estado atual do provedor, sem repetir a transição de pagamento. Respostas não exitosas permitem retentativa pelo provedor.

A chave de idempotência é persistida antes da chamada externa e reutilizada após timeout. O worker repete o mesmo snapshot com a mesma chave; se houver falha ao receber a aceitação HTTP, repita o POST com os mesmos dados; a API rejeita trocar os dados de uma tentativa existente. Há uma cobrança por pedido nesta etapa. Não gere uma segunda tentativa com outro token após resultado incerto. Enquanto existir tentativa, o cancelamento local retorna 409 para não liberar estoque de cobrança pagável. Cancelamento/estorno remoto e novas tentativas após rejeição precisam de fluxo operacional próprio; não há liberação automática de estoque por eventos financeiros.

## Validação e limites

Os testes usam `Http::fake`, sem cobranças reais. A homologação precisa verificar credenciais, recursos e meios habilitados na conta, parcelas, tarifas, Pix e cartões de teste. Crédito/débito dependem da disponibilidade da conta.

O checkout React integra a API e o MercadoPago.js v2, carregado do domínio oficial. PAN/CVV e validade ficam nos iframes do provedor e nunca passam pela API da loja. A Public Key vem da configuração de ambiente por endpoint autenticado e sem cache; Access Token e segredo do webhook permanecem exclusivamente no servidor. Não foram adicionadas dependências ou substituído o cliente HTTP Laravel existente.

Referências oficiais:

- [Modelo de Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-model)
- [Pix](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/websites/pix)
- [Notificações de Orders](https://www.mercadopago.com.br/developers/en/docs/checkout-api-orders/notifications)
- [Validador oficial de assinatura](https://github.com/mercadopago/sdk-php/blob/master/src/MercadoPago/Webhook/WebhookSignatureValidator.php)

## Checkout e tokenização React

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

Aplique a migration `2026_10_18_120000_add_processamento_to_pedido_pagamentos` e reinicie o Horizon para carregar o supervisor `pagamentos`. Ele usa conexão Redis, timeout de 45 segundos por job, oito tentativas com backoff de 10/30/60/120 segundos, exclusão mútua por pagamento e somente o identificador local no payload. A publicação acontece após commit. Se o Redis falhar, a solicitação continua no PostgreSQL e `pagamentos:retomar`, executado a cada minuto, recupera registros parados há dois minutos. O scheduler também retoma a conciliação de Orders pendentes, sem depender de navegador aberto ou webhook.

`processamento` representa o estado local: `pendente`, `processando`, `aguardando` retorno remoto, `concluido` ou `verificacao_necessaria`. O campo `status` continua representando a Order. Sem Order ID e após esgotar tentativas ou ultrapassar dez minutos desde a solicitação, o resultado é incerto: requer verificação operacional e não é declarado como rejeição. O token efêmero é apagado e uma nova cobrança não é autorizada. Com Order ID, a conciliação continua pelo scheduler. Tentativas legadas sem snapshot nem Order ID também exigem verificação. O webhook assinado continua consultando o recurso do provedor e pode recuperar uma Order criada antes de um timeout.

Os endpoints de pedido incluem apenas o resumo `pagamento` (`id`, `pedido_id`, `status`, `processamento`). A página de detalhes atualiza esse estado em segundo plano a cada dez segundos enquanto o pagamento estiver em andamento. Nenhuma confirmação do navegador altera o status financeiro do pedido. A emissão fiscal continua sendo liberada somente pela confirmação validada do provedor.