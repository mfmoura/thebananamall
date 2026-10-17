# Loja Virtual LCB

API da loja virtual de artigos da Liga Comunista Brasileira (LCB).

## Histórico do projeto

### Sprint inicial — 15/10/2026

- Modelagem inicial das classes e dos casos de uso.
- Definição dos requisitos funcionais e apresentação aos stakeholders.
- Configuração das ferramentas de gestão e modelagem do banco de dados.

### Desenvolvimento — 16/10/2026

- Implementação da API Laravel com PostgreSQL e Redis, seguindo a separação entre controllers, FormRequests, services e models.
- Implementação dos perfis de usuário Cliente, Administrador e Logística, autenticação por token e endereços.
- Implementação de carrinho no Redis e checkout persistido no PostgreSQL, com itens de pedido que preservam o preço usado na compra.
- Implementação de catálogo de produtos, categorias, imagens, preços históricos e movimentações de estoque por entradas, saídas e estornos.
- Implementação dos models e migrations para notas fiscais e itens de nota, com enums de status e operação. A emissão ainda depende de integração com provedor fiscal.
- Migração da autenticação da API para OAuth2 com Passport, Authorization Code + PKCE e escopos distintos para Cliente e Administrador.
- Criação de Visitantes persistidos no banco com credencial opaca temporária para carrinho público; após autenticação OAuth2, o Cliente pode mesclar o carrinho de Visitante ao seu carrinho ativo.
- Atualização da collection e das instruções de testes manuais no Postman.

O fluxo de checkout cria o pedido como `aguardando_pagamento`, consome a reserva do carrinho e registra uma saída de estoque. A disponibilidade pública desconta do estoque contado as quantidades nos carrinhos Redis ativos; carrinhos e reservas compartilham TTL e as alterações, saídas administrativas e checkout são serializados por lock distribuído. A cobrança por gateway ainda não está integrada. Também faltam emissão e consulta de DANFE e operações de expedição para o perfil Logística. Consulte [TODO.md](TODO.md) para as etapas pendentes.

O catálogo, a consulta de preços e a disponibilidade de estoque são públicos. O campo `estoque` representa o saldo contado menos as reservas em carrinhos ativos; a consulta individual também informa `estoque_contado` e `estoque_disponivel`. Para usar carrinho sem login, inicialize uma identidade em `POST /api/visitantes` e envie o token retornado em `X-Visitante-Token`. O checkout continua restrito a Cliente autenticado por OAuth2. O BFF usa um cliente OAuth confidencial e PKCE no servidor; instale-o com `docker compose exec php php artisan bff:install`. Tokens e credenciais de visitante ficam cifrados na sessão do servidor, acessada por cookie HttpOnly. O frontend fornece a tela de login; a API mantém a sessão web necessária ao fluxo de autorização em `/oauth/login`.

Administradores recebem as páginas `contabilidade` e/ou `estoque`, persistidas no campo JSON `administradores.paginas`. As rotas de cadastro e gestão de Administradores exigem Contabilidade; alterações de produtos, preços, imagens e estoque exigem Estoque. Um Administrador só pode conceder páginas que já possui.

Novas contas ficam com `email_validado=false` e só podem obter sessão OAuth depois de confirmar o e-mail. O token de confirmação e o e-mail pendente são guardados no Redis pelo tempo de `EMAIL_VERIFICATION_TTL` (300 segundos no ambiente local). Trocas de e-mail usam `POST /api/usuarios/{id}/email`; o link abre o frontend em `/email/verify?token=...`, que confirma por `POST /api/email/confirmar`. Ao confirmar uma troca, a tela também envia uma nova senha, os tokens existentes são revogados e o usuário deve entrar novamente. Links expirados podem ser reenviados por `POST /api/email/reenviar-confirmacao`.

O Administrador inicial de desenvolvimento usa `ADMIN_EMAIL` e `ADMIN_INITIAL_PASSWORD` em `.env`. Após confirmar o e-mail, o primeiro login exige a troca dessa senha em `POST /oauth/first-login/password`; o backend não concede sessão ou token até a pessoa trocar a senha e entrar novamente. Configure `MAIL_*` localmente para o SMTP desejado; não copie credenciais para `.env.example`.

Para envio via Gmail, use uma senha de app quando a autenticação SMTP recusar a senha normal da conta. A [ajuda oficial do Google](https://support.google.com/mail/answer/185833?hl=pt-BR) explica os requisitos de senhas de app.

## Desenvolvimento local

A loja usa a família **Breda Two**, identificada no CSS do site oficial da LCB. O arquivo WOFF2 fica em `resources/fonts/breda-two.woff2` e é servido localmente pelo Vite, sem depender do site externo durante a navegação.

O componente `ControleQuantidade` oferece botões de aumentar/diminuir e entrada numérica no catálogo e no detalhe. No menu flutuante, no carrinho e no checkout, `ItemCarrinho` permite alterar a quantidade ou remover o produto. Alterações do carrinho atualizam o estado e o total compartilhados após confirmação da API; operações concorrentes da interface ficam bloqueadas durante a gravação. O servidor continua validando o estoque.

Produtos possuem `descricao` textual opcional (até 10000 caracteres na API), exibida no detalhe da loja. Após a migration, `docker compose exec php php artisan db:seed --class=ProdutoDescricaoSeeder` preenche textos dos produtos existentes sem sobrescrever descrições já cadastradas; também integra o seeder completo.

A homepage mostra o catálogo React. O hook `usePaginacao` carrega cinco produtos por requisição (`GET /api/produtos?per_page=5&page=1`), busca as próximas páginas ao rolar e oferece botão manual/repetição em caso de falha. Imagens dos cards usam lazy loading. O detalhe exibe todas as imagens em um único carrossel com controles acessíveis, começando pela imagem principal. O cadastro promove a primeira imagem a principal; substituir ou excluir a principal mantém outra imagem nessa função enquanto houver imagens.

Para adicionar 12 produtos de demonstração (camisetas, regata, moletom, cropped e bottons) com 36 ilustrações SVG locais, execute `docker compose exec php php artisan db:seed --class=ProdutoCatalogoSeeder`. O seeder roda somente em `local`/`testing`, preserva preços existentes e não repõe estoque em execuções repetidas. As imagens em `public/images/produtos` são mockups de teste.

A página inicial em `http://localhost` exibe o catálogo React com rolagem infinita. O container `node` executa `npm run dev` na porta 5173; mantenha-o ativo para carregar os assets durante o desenvolvimento.

O Vite escuta em `0.0.0.0` dentro do Docker e publica assets/HMR em `http://localhost:5173`. O polling detecta edições no volume Windows. Após alterar sua configuração, reinicie com `docker compose restart node`. Essa URL de desenvolvimento é local; acesso externo por HTTPS/ngrok deve usar assets compilados ou um proxy HTTPS específico para o Vite.

O frontend usa React 19 com Vite. A entrada é `resources/js/main.jsx`, o layout fica em `resources/js/LojaApp.jsx`, a barra flutuante em `resources/js/components/BarraSuperior.jsx` e o catálogo/detalhe em `resources/js/pages/Catalogo.jsx`. Hooks de menus e resumo do carrinho ficam em `resources/js/hooks/loja.js`. Blade fornece somente o documento HTML e o ponto de montagem React. A identidade visual e o logo vêm do [site oficial da LCB](https://ligacomunistabrasileira.org/); ícones Font Awesome são instalados pelo NPM. O React Router gerencia catálogo, detalhe de produto, carrinho, cadastro, login OAuth2/PKCE, confirmação de e-mail, recuperação de senha, conta, endereços, checkout e pedidos. Os serviços HTTP ficam em `resources/js/services` e o estado compartilhado em `resources/js/context/LojaContext.jsx`. A barra lê o carrinho real da API; o navegador não determina preços nem autoriza pedidos. Pagamento real pelo gateway e painéis administrativos/Logística continuam pendentes.

Inicie os containers e aplique as migrations e dados de demonstração:

```bash
docker compose up -d
docker compose exec php php artisan migrate --seed
```

As instruções e contas locais de teste estão em [`postman/README.md`](postman/README.md); a collection para importar está em [`postman/LojaVirtualLCB.postman_collection.json`](postman/LojaVirtualLCB.postman_collection.json).

## Webhooks locais com ngrok

O serviço opcional `ngrok` encaminha uma URL pública HTTPS para a API no Nginx. Crie sua conta em [ngrok](https://dashboard.ngrok.com/signup) e copie o [authtoken](https://dashboard.ngrok.com/get-started/your-authtoken) para `NGROK_AUTHTOKEN` no `.env` local.

```bash
docker compose --profile webhooks up -d ngrok
docker compose logs ngrok
```

A URL pública aparece nos logs e no inspetor em [http://localhost:4040](http://localhost:4040). Cadastre no provedor a URL HTTPS seguida do caminho do endpoint de webhook. Os endpoints de recebimento e validação de webhooks de pagamento e fiscais ainda estão pendentes; o túnel fornece a conectividade para implementá-los e testá-los.

Para parar o túnel, execute `docker compose stop ngrok`. Depois de alterar o token, execute `docker compose --profile webhooks up -d --force-recreate ngrok`. O serviço só inicia quando solicitado pelo perfil ou pelo nome.

## Documentação base

Diagrama de classes e casos de uso:

https://lucid.app/lucidchart/bc14f3ed-2cf3-4dac-ba61-31c056992e16/edit?viewport_loc=-652%2C-104%2C2365%2C1227%2CE9IDngY-3BPj&invitationId=inv_36efa41a-d4c7-48a9-aee8-d744df83e09e

## Integrações planejadas

A compra retorna imediatamente com `processamento_fiscal` e a mensagem **Seus dados estão sendo processados**. Após confirmação de pagamento pelo backend, jobs Redis gerenciados pelo **Laravel Horizon** emitem a NF-e e geram o DANFE em segundo plano. Os containers `horizon` e `scheduler` sobem pelo Docker Compose. O checkout exige `dados_fiscais`, e os produtos usam `configuracao_fiscal` administrativa; veja o guia fiscal abaixo. A integração real do gateway de pagamento permanece pendente.

O pagamento planejado usa Mercado Pago com Checkout Transparente e tokenização dos cartões com MercadoPago.js. A integração fiscal com **Fiscal Contora já está implementada para homologação**: criação e transmissão de NF-e, consulta de status, XML privado e geração local do DANFE em PDF. Configure a conta e o certificado no provedor e as credenciais no `.env` para testar de ponta a ponta. Veja [docs/FISCAL-CONTORA.md](docs/FISCAL-CONTORA.md). Notas de homologação não movimentam estoque real. Produção, cancelamento e webhooks fiscais continuam no [TODO.md](TODO.md).

## Contrato de respostas da API

Downloads fiscais de XML e DANFE retornam o arquivo binário com Content-Disposition em caso de sucesso; seus erros seguem o envelope JSON abaixo.

Todas as rotas REST em `/api/*` respondem com o envelope abaixo. `status` é `success` ou `error`; `statusCode` repete o código HTTP real; `data` contém os dados serializados, `null` quando não há dados ou os detalhes do erro.

```json
{
  "status": "success",
  "statusCode": 200,
  "data": {
    "id": 12
  }
}
```

Erros de validação seguem o mesmo envelope, com `data.message` e `data.errors`:

```json
{
  "status": "error",
  "statusCode": 422,
  "data": {
    "message": "Os dados informados são inválidos.",
    "errors": {
      "email": ["O campo email deve ser um endereço válido."]
    }
  }
}
```

Exclusões também retornam JSON com `statusCode: 200` e `data: null`; a API não usa HTTP 204 porque respostas 204 não podem conter corpo. A especificação OpenAPI está em [docs/openapi.yaml](docs/openapi.yaml) e descreve endpoints, autenticação, payloads e erros.

## Fluxo do cliente no React

Abra http://localhost/produtos. Cadastre a conta em /cadastro, confirme o e-mail e entre em /login. O carrinho de visitante é mesclado após o OAuth. Conta e endereços ficam em /conta; compras em /checkout; pedidos e DANFE em /pedidos. O checkout cria um pedido aguardando pagamento e reutiliza uma chave de idempotência nas retentativas. Não recebe PAN/CVV nem simula cobrança.

Configure APP_URL=http://localhost e FRONTEND_URL=http://localhost no ambiente local. Execute docker compose exec php php artisan bff:install para configurar o cliente confidencial; seu callback é /bff/callback. O Vite continua na porta 5173 somente para assets. O React usa /bff/api/* com cookie HttpOnly e CSRF. Access/refresh tokens, verifier PKCE e credenciais de visitante permanecem no servidor. Logout revoga os tokens e invalida a sessão. Veja [docs/BFF.md](docs/BFF.md). A instalação revoga o antigo cliente público da SPA e exige um novo login.
