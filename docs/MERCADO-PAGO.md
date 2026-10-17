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

Execute `docker compose exec php php artisan mercadopago:verificar` para verificar as três variáveis sem imprimir seus valores, acessar a conta ou criar cobranças. O comando retorna erro quando alguma variável está ausente. A Public Key prepara a etapa de tokenização no frontend; não habilita sozinha o formulário de cartões.

Obtenha as credenciais na aplicação do [painel Mercado Pago](https://www.mercadopago.com.br/developers/panel/app). Para homologação, use as credenciais e contas de teste indicadas para Orders. Não compartilhe Access Token ou segredo do webhook no chat ou no Postman. O segredo é fornecido pelo painel ao configurar as notificações; não gere um valor aleatório local em seu lugar.

O diagnóstico verifica preenchimento, não validade das credenciais ou entrega das notificações. Se APP_URL for local, ele orienta cadastrar uma URL pública HTTPS; isso não impede testes locais da API. A Public Key continua somente na configuração Laravel nesta etapa, sem publicação no navegador. Antes de produção, conclua a homologação e a revisão da integração (`mp-review`).

Com conta pronta, perfil Cliente e escopo `orders:write`:

- `POST /api/pedidos/{pedido}/pagamento`: cria uma única cobrança por pedido. Pix: `{"meio":"pix"}`. Cartão: `{"meio":"credit_card","payment_method_id":"master","token":"TOKEN_GERADO_PELO_PROVEDOR","parcelas":1}`. Débito usa `debit_card` e uma parcela. O meio deve corresponder à forma cadastrada no pedido (`pix`, `cartao_credito`, `cartao_debito`).
- `GET /api/pedidos/{pedido}/pagamento`: consulta a cobrança e reconcilia o pedido. Ambos os endpoints verificam propriedade. Estão também na lista explícita do BFF, em `/bff/api/pedidos/{pedido}/pagamento`, preservando cookie/CSRF e os middlewares da API.
- `POST /api/webhooks/mercado-pago?data.id=ORD...`: exige `x-signature` e `x-request-id`, valida HMAC SHA-256 e consulta o ID assinado. O corpo não confirma pagamentos. A assinatura preserva a caixa do ID, conforme o código atual do SDK oficial.

As respostas seguem o envelope existente. A cobrança retorna `order_id`, `status` e `instrucoes` com os campos Pix disponíveis (`qr_code`, `qr_code_base64`, `ticket_url`). Chaves internas e hashes não são serializados. As instruções são cifradas no banco; tokens de cartão não são persistidos.

Somente Orders com status `processed/accredited`, moeda BRL, referência do pedido, total e total pago correspondentes confirmam o pagamento. O observer existente libera o processamento fiscal após commit. Eventos repetidos consultam o estado atual do provedor, sem repetir a transição de pagamento. Respostas não exitosas permitem retentativa pelo provedor.

A chave de idempotência é persistida antes da chamada externa e reutilizada após timeout. Repita o POST com os mesmos dados; a API rejeita trocar os dados de uma tentativa existente. Há uma cobrança por pedido nesta etapa. Não gere uma segunda tentativa com outro token após resultado incerto. Enquanto existir tentativa, o cancelamento local retorna 409 para não liberar estoque de cobrança pagável. Cancelamento/estorno remoto e novas tentativas após rejeição precisam de fluxo operacional próprio; não há liberação automática de estoque por eventos financeiros.

## Validação e limites

Os testes usam `Http::fake`, sem cobranças reais. A homologação precisa verificar credenciais, recursos e meios habilitados na conta, parcelas, tarifas, Pix e cartões de teste. Crédito/débito dependem da disponibilidade da conta.

Esta entrega integra a API. O formulário React de tokenização MercadoPago.js e a apresentação do Pix ainda precisam ser conectados aos endpoints. PAN/CVV nunca devem passar pela API da loja. A Public Key será necessária nessa etapa e deve vir da configuração de ambiente; Access Token e segredo do webhook permanecem exclusivamente no servidor.

Referências oficiais:

- [Modelo de Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-model)
- [Pix](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/websites/pix)
- [Notificações de Orders](https://www.mercadopago.com.br/developers/en/docs/checkout-api-orders/notifications)
- [Validador oficial de assinatura](https://github.com/mercadopago/sdk-php/blob/master/src/MercadoPago/Webhook/WebhookSignatureValidator.php)
