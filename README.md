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

O cadastro usa senhas de 8 a 15 caracteres, com a mesma validação no React e na API. Após criar a conta, o usuário é redirecionado para a página de confirmação de e-mail, com opção de reenvio do link. O intervalo também se aplica às novas senhas nos fluxos de troca e recuperação; o login mantém compatibilidade com senhas existentes.

O idioma da aplicação é `pt_BR`, com traduções Laravel em `lang/pt_BR` e mensagens de validação nativa e conexão em português no React. Configure `APP_LOCALE=pt_BR` e `APP_FALLBACK_LOCALE=pt_BR`; após alterar esses valores, execute `docker compose exec php php artisan config:clear`.

O template React `resources/js/components/Rodape.jsx` compartilha o rodapé institucional da Liga em todas as páginas da loja, com logo local, links institucionais e redes sociais oficiais. O catálogo e o detalhe usam `ProdutoSkeleton` durante o carregamento; ao terminar a paginação, os placeholders e o controle de carregar mais são removidos.

A loja usa a família **Breda Two**, identificada no CSS do site oficial da LCB. O arquivo WOFF2 fica em `resources/fonts/breda-two.woff2` e é servido localmente pelo Vite, sem depender do site externo durante a navegação.

O componente `ControleQuantidade` oferece botões de aumentar/diminuir e entrada numérica no catálogo e no detalhe. No menu flutuante, no carrinho e no checkout, `ItemCarrinho` permite alterar a quantidade ou remover o produto. Alterações do carrinho atualizam o estado e o total compartilhados após confirmação da API; operações concorrentes da interface ficam bloqueadas durante a gravação. O servidor continua validando o estoque.

Produtos possuem `descricao` textual opcional (até 10000 caracteres na API), exibida no detalhe da loja. Após a migration, `docker compose exec php php artisan db:seed --class=ProdutoDescricaoSeeder` preenche textos dos produtos existentes sem sobrescrever descrições já cadastradas; também integra o seeder completo.

A homepage mostra o catálogo React. O hook `usePaginacao` carrega cinco produtos por requisição (`GET /api/produtos?per_page=5&page=1`), busca as próximas páginas ao rolar e oferece botão manual/repetição em caso de falha. Imagens dos cards usam lazy loading. O detalhe exibe todas as imagens em um único carrossel com controles acessíveis, começando pela imagem principal. O cadastro promove a primeira imagem a principal; substituir ou excluir a principal mantém outra imagem nessa função enquanto houver imagens.

Para adicionar 12 produtos de demonstração (camisetas, regata, moletom, cropped e bottons) com 36 ilustrações SVG locais, execute `docker compose exec php php artisan db:seed --class=ProdutoCatalogoSeeder`. O seeder roda somente em `local`/`testing`, preserva preços existentes e não repõe estoque em execuções repetidas. As imagens em `public/images/produtos` são mockups de teste.

A página inicial em `http://localhost` exibe o catálogo React com rolagem infinita. O container `node` executa `npm run dev` na porta interna 5173; mantenha-o ativo para carregar os assets durante o desenvolvimento. Assets e HMR passam pelo Nginx em `http://localhost/vite/`.

O Vite escuta em `0.0.0.0` dentro do Docker, sem porta publicada no host. O polling detecta edições no volume Windows. Após alterar sua configuração, reinicie com `docker compose restart node`. O desenvolvimento usa `http://localhost`; acesso externo por HTTPS/ngrok deve usar assets compilados, pois a origem de desenvolvimento e o HMR continuam configurados para localhost. Em produção, compile com `docker compose run --rm --no-deps node npm run build`, pare o serviço `node` e remova `public/hot` caso tenha restado após uma interrupção abrupta.

O Nginx é a única entrada HTTP da aplicação. As redes `php_ingress` e `vite_ingress` conectam apenas Nginx e seu respectivo upstream. PostgreSQL e Redis ficam na rede `dados`, marcada como interna, acessível ao PHP, Horizon e scheduler; o Nginx e o Node não participam dela. O banco publica a porta 5432 somente no loopback do host (`127.0.0.1:5432`), para administração pelo próprio computador. Também é possível usar `docker compose exec db psql -U default -d default`. PHP, Node e workers preservam saída para suas integrações externas. O perfil opcional `webhooks` conecta o ngrok somente à rede `tunnel` do Nginx; seu painel de diagnóstico permanece restrito a `127.0.0.1:4040`. Essas redes controlam comunicação entre containers; o host Docker e quem controla o daemon continuam sendo acessos privilegiados.

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

Configure APP_URL=http://localhost e FRONTEND_URL=http://localhost no ambiente local. Execute docker compose exec php php artisan bff:install para configurar o cliente confidencial; seu callback é /bff/callback. O Vite usa a porta interna 5173; assets e HMR passam por `/vite/` no Nginx. O React usa /bff/api/* com cookie HttpOnly e CSRF. Access/refresh tokens, verifier PKCE e credenciais de visitante permanecem no servidor. Logout revoga os tokens e invalida a sessão. Veja [docs/BFF.md](docs/BFF.md). A instalação revoga o antigo cliente público da SPA e exige um novo login.

### Exclusão lógica e confirmação de cadastro

Os dados de domínio no PostgreSQL usam `deleted_at`. Endpoints de exclusão mantêm o formato de resposta e deixam de retornar registros excluídos. Login pode ser reutilizado após exclusão da conta. E-mails não são reservados por cadastros pendentes; somente contas ativas confirmadas exigem unicidade. Pedidos e documentos históricos são preservados.

Contas não confirmadas, sem login e com prazo de confirmação vencido são excluídas logicamente pelo scheduler a cada minuto (`php artisan usuarios:limpar-expirados`). Visitantes são isentos. Reenvio antes do vencimento renova o prazo; após o vencimento é necessário refazer o cadastro. Carrinhos Redis continuam com TTL e são liberados ao excluir a conta.
Cadastros pendentes podem compartilhar e-mail. A confirmação verifica a unicidade atomicamente: somente a primeira conta confirmada reserva o endereço. Reenvio por e-mail seleciona o cadastro pendente mais recente ainda válido; recuperação de senha usa somente a conta confirmada.

### Diagnóstico local de desempenho

Telescope: http://localhost/telescope. Medições repetíveis: `docker compose exec node npm run perf`. Resultados, limitações e configuração em [docs/DESEMPENHO.md](docs/DESEMPENHO.md).

Os dados de domínio excluídos logicamente são preservados permanentemente para relatórios futuros. Não há expurgo desses registros; relatórios poderão incluí-los explicitamente, respeitando as permissões de acesso. Dados temporários de Redis e diagnóstico seguem suas regras próprias de expiração.

### Carrinhos abandonados e reservas

- Carrinho visitante: 24 horas sem alterações (`CARRINHO_VISITANTE_TTL=86400`); Cliente: 30 dias (`CARRINHO_CLIENTE_TTL=2592000`).
- Reserva de estoque: 15 minutos após alteração efetiva dos itens (`CARRINHO_RESERVA_TTL=900`). GET, criação de carrinho já existente e reenvio das mesmas quantidades não renovam prazos.
- Após expirar a reserva, os itens permanecem no carrinho até seu próprio TTL. A API inclui `reserva_expira_em` (Unix timestamp) e `reserva_ativa`; o frontend avisa sobre a expiração.
- Checkout revalida preços e saldo descontando reservas de outros carrinhos; reserva vencida não garante estoque. Limpeza e checkout usam o mesmo bloqueio global.
- O scheduler executa `carrinhos:sanitizar` a cada minuto, usa SCAN e libera reservas vencidas, inválidas ou sem item/carrinho correspondente. A adequação inicial infere a idade dos carrinhos antigos pelo TTL restante e aplica os novos prazos sem registrar nova atividade. `CARRINHO_TTL` é mantido como referência do TTL legado durante essa transição.
- Manualmente: `docker compose exec php php artisan carrinhos:sanitizar`. Redis temporário continua fora da política de retenção permanente dos registros PostgreSQL.
A política de senhas usa validação local e histórico de hashes; cadastro e troca de senha não consultam mais a API Pwned Passwords.

### E-mails assíncronos

Confirmação e troca de e-mail, recuperação de senha e alerta de senha antiga usam notificações enfileiradas na conexão Redis, fila `emails`, com supervisor próprio no Horizon. O payload é cifrado; envio ocorre após commit, com três tentativas, backoff de 10/30/60 segundos e timeout SMTP de 20 segundos (`MAIL_TIMEOUT`). Não executar SMTP na requisição HTTP. O Horizon precisa estar ativo.

A validade dos links continua contando desde a solicitação (5 minutos para confirmação e 10 para recuperação no ambiente local); a fila não renova tokens. Antes de enviar, o worker descarta links vencidos/consumidos e notificações de contas excluídas. O texto do e-mail informa que a validade começa na solicitação. Monitorar atrasos da fila, com limiar de 30 segundos; não reenviar jobs com links vencidos como se fossem links novos. Falhas definitivas ficam disponíveis no Horizon para diagnóstico. As respostas HTTP indicam aceitação da solicitação, sem garantir entrega imediata ao destinatário.
O Compose define limites de memória por serviço, heap Node, teto Redis sem eviction e PCOV desativado fora de cobertura. Ajustes por `DOCKER_*_MEMORY`, `NODE_HEAP_MB` e `REDIS_MAXMEMORY` no `.env.example`; detalhes em [docs/DESEMPENHO.md](docs/DESEMPENHO.md).

Em `/conta`, o formulário de endereço consulta o ViaCEP ao completar o CEP (oito dígitos, com ou sem hífen). Preenche logradouro, cidade e UF; número e complemento permanecem manuais. O React usa `GET /bff/api/enderecos/cep/{cep}`; integrações usam `GET /api/enderecos/cep/{cep}` com OAuth. Requer conta liberada, limita a 30 consultas por minuto e cancela respostas de CEPs anteriores. A consulta não salva o endereço nem substitui a validação no servidor.

Comparação do volume Windows com filesystem Linux: [relatório e resultados](docs/COMPARACAO-FILESYSTEM.md).
