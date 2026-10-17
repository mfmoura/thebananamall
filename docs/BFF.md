# BFF da loja

O React acessa a API por `/bff/api/*`, usando a sessão Laravel em cookie **HttpOnly**, **SameSite=Lax** e **Secure** fora do ambiente `local`. A sessão usa armazenamento no servidor (atualmente PostgreSQL). O driver `cookie` é recusado. A credencial de visitante, o access token e o refresh token são cifrados com a chave da aplicação antes de serem guardados na sessão; não são devolvidos ao JavaScript.

## Instalação

```bash
docker compose exec php php artisan bff:install
```

Se o usuário PHP não puder gravar o `.env` na primeira instalação, configure `BFF_CLIENT_SECRET` no ambiente com um valor aleatório de pelo menos 32 caracteres, ou execute essa instalação com `docker compose exec --user root php php artisan bff:install`. O comando cria a credencial sem imprimi-la e cadastra o cliente confidencial. `BFF_CLIENT_ID` possui um UUID padrão. Nunca use `VITE_` no nome de um segredo.

O callback registrado é `APP_URL/bff/callback`; configure `APP_URL` com a origem HTTPS real em produção e execute a instalação novamente se a origem ou a credencial mudar. A API REST continua disponível com seu OAuth e permissões existentes para integrações com clientes próprios. O antigo cliente público da SPA e seus tokens são revogados pela instalação; faça login novamente após a atualização. Rodar o seeder completo não reativa esse cliente antigo.

## Fluxo

1. `GET /oauth/csrf` inicia a sessão e devolve o token CSRF (que não é uma credencial OAuth).
2. O formulário faz `POST /oauth/login`, com `X-CSRF-TOKEN`, para validar as credenciais no Laravel.
3. `POST /bff/authorize`, também protegido por CSRF e login, gera `state` e verifier PKCE no servidor e devolve somente `authorization_url`.
4. Passport redireciona para `GET /bff/callback`. O BFF valida state de uso único e prazo de dez minutos, troca o código com o secret e o verifier no servidor e regenera o ID da sessão.
5. O navegador volta a `/oauth/callback` sem código OAuth ou tokens. React consulta `/bff/session` e mescla o carrinho de visitante por `/bff/api/carrinho/mesclar`.
6. Chamadas `/bff/api/*` recebem o bearer somente dentro do servidor e passam pelos mesmos middlewares de autorização da API. A renovação usa refresh token, com bloqueio por sessão para evitar concorrência. Não há proxy para hosts externos, endpoints OAuth, administração ou caminhos arbitrários.
7. `POST /bff/logout` revoga access/refresh tokens, invalida a sessão web e renova o CSRF. Todas as escritas BFF exigem CSRF. Nenhum endpoint devolve credenciais de autenticação.

O carrinho anônimo é criado sob demanda; a credencial fica na sessão. `POST /bff/visitante` permite migrar uma credencial válida deixada pela versão antiga no localStorage, sem devolvê-la. O frontend remove essa chave após a migração e elimina os antigos valores OAuth do sessionStorage.

As respostas proxy preservam o envelope JSON da API e os downloads de DANFE. `/bff/session` retorna somente `{ "autenticado": true|false }`; `/bff/authorize` retorna `{ "authorization_url": "..." }`. Erros BFF usam o envelope da aplicação. Respostas são privadas e não devem ser armazenadas em cache.

Referência de arquitetura: [OAuth 2.0 for Browser-Based Applications, seção BFF](https://www.rfc-editor.org/rfc/rfc10017.html#section-6.1).

## Chaves OAuth e autorização

O PHP precisa ler `storage/oauth-private.key` e `storage/oauth-public.key`. No Compose atual, o proprietário deve ser `1000:1000`; as duas chaves usam permissão `600`. Se as chaves foram criadas como root, corrigir o proprietário sem regenerá-las:

```sh
docker compose exec -u root php chown 1000:1000 storage/oauth-private.key storage/oauth-public.key
docker compose exec -u root php chmod 600 storage/oauth-private.key
docker compose exec -u root php chmod 600 storage/oauth-public.key
```

Gerar chaves como o usuário padrão do container; não usar `passport:keys --force` para resolver permissões. `AppServiceProvider` registra `Passport::authorizationView` porque o contrato é resolvido antes da dispensa de consentimento. O cliente BFF é autorizado pelo critério existente em `OAuthClient::skipsAuthorization`; clientes que exigem consentimento manual recebem 403 até terem um fluxo próprio.