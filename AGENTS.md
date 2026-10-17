# Instruções de desenvolvimento

Este arquivo registra as convenções do projeto para qualquer pessoa ou LLM que contribua com o código. Leia-o antes de implementar mudanças.

## Contexto

- O projeto é uma API Laravel. Não criar páginas ou fluxos de interface web sem solicitação explícita.
- O modelo de usuário da aplicação é `App\Models\Usuario`, armazenado atualmente na tabela `users`.
- O projeto usa PostgreSQL e Redis via Docker Compose. Não altere a infraestrutura ou suas credenciais sem necessidade para a tarefa.
- O projeto busca seguir boas práticas e os princípios SOLID; prefira mudanças pequenas, coesas e fáceis de manter.

## Organização e responsabilidades

- **Frontend React (`resources/js`)**: use componentes JSX reutilizáveis em `components`, páginas em `pages` e hooks em `hooks`. `main.jsx` monta a aplicação; `LojaApp.jsx` compartilha o layout e a barra flutuante. Blade serve somente o documento de entrada. Preserve o visual da LCB e o Font Awesome; mantenha autenticação e regras de negócio na API.
- **Navegação e integração**: páginas do cliente usam React Router e serviços em `resources/js/services`; o contexto `LojaContext` compartilha conta e carrinho. Consumir o envelope da API em um único cliente HTTP, exibir validações nos formulários e usar Authorization Code + PKCE. Não confiar no estado React para autorização, preços, disponibilidade ou confirmação de pagamento. As rotas de fallback do Laravel são restritas às páginas, sem interceptar `/api/*`, endpoints Passport ou arquivos.

- **Controllers (`app/Http/Controllers/Api`)**: devem coordenar a requisição e a resposta HTTP. Mantenha-os finos: não coloque neles regras de negócio, validação inline ou operações de persistência.
- **Services (`app/Services`)**: concentram operações de negócio e processamento reutilizável. Controllers devem delegar essas operações aos services, em vez de duplicar lógica.
- **Jobs (`app/Jobs`)**: processamento fiscal de pedidos acontece na fila Redis `fiscal`, gerenciada pelo Horizon, após confirmação de pagamento e commit da transação. Jobs carregam somente identificadores; dados fiscais ficam cifrados no registro persistente de acompanhamento. Preserve idempotência, snapshots de preço/tributação e retomada da mesma nota em retentativas. Não chame o provedor fiscal na requisição de compra nem exponha erros técnicos ao Cliente.
- **FormRequests (`app/Http/Requests/Api`)**: encapsulam validação e autorização específicas da requisição. Use classes distintas quando as regras ou permissões forem diferentes; controllers devem consumir `$request->validated()`.
- **Models (`app/Models`)**: representam os dados e relacionamentos do Eloquent. Mantenha campos graváveis, dados ocultos e casts explícitos e coerentes.
- **Rotas (`routes/api.php`)**: mantenha os endpoints da API nesse arquivo e aplique autenticação e middleware na rota ou no grupo apropriado.
- **Postman (`postman/LojaVirtualLCB.postman_collection.json`)**: sempre que uma mudança adicionar ou alterar uma função acessível pela API, atualize a coleção com a requisição, autenticação, payload e variáveis necessárias. Mantenha as instruções em `postman/README.md` coerentes com os fluxos disponíveis.

## Convenções

- **BFF**: o React chama `/bff/api/*` usando cookie HttpOnly e CSRF. Access/refresh tokens e credenciais de visitante ficam cifrados na sessão do servidor; nunca os retorne em JSON, HTML ou armazenamento do navegador. OAuth usa cliente confidencial e PKCE no servidor. Preserve a lista explícita de rotas do proxy, os middlewares originais da API e o bloqueio por sessão na renovação. O driver de sessão `cookie` é incompatível com esse padrão. Veja `docs/BFF.md`.

- Siga os padrões e versões já presentes no projeto antes de introduzir dependências ou arquiteturas novas.
- Use nomes em português para conceitos de domínio já nomeados em português, como `Usuario`; preserve os nomes convencionais do Laravel quando fizerem parte de APIs ou ferramentas do framework.
- Injete dependências pelo container do Laravel. Evite acoplamento desnecessário entre controller, banco de dados e regras de negócio.
- Respostas HTTP pertencem à camada de apresentação. Não invente um envelope global ou altere o formato existente sem solicitação; o formato padrão de resposta da API ainda é uma decisão que o responsável pelo projeto quer controlar.
- Senhas devem ser armazenadas usando o hash seguro do Laravel (cast `hashed` ou `Hash`). **Base64 não é hash e não deve ser usado para proteger senhas.** Nunca retorne senhas ou tokens secretos em respostas.
- Preserve autorização por usuário nas operações protegidas. Não remova verificações de propriedade ao refatorar endpoints.
- Evite abstrações genéricas ou camadas adicionais sem uma necessidade concreta demonstrada pelo código.

## Testes e alterações

- Escreva testes PHPUnit de feature para comportamentos de endpoints e testes unitários para lógica isolada quando isso trouxer cobertura útil.
- Ao alterar um comportamento existente, atualize ou acrescente testes que cubram o caminho esperado e falhas relevantes.
- Execute os testes pertinentes quando solicitado e reporte claramente o resultado ou qualquer impedimento do ambiente.
- Não reverta mudanças locais preexistentes nem modifique arquivos fora do escopo necessário.

## Exclusão lógica e expiração de contas

- Todo modelo de domínio persistido no PostgreSQL deve usar `SoftDeletes` e uma coluna `deleted_at`. Exclusões comuns não podem usar `forceDelete`, `truncate` ou exclusão física por SQL. Novos modelos e migrations devem seguir essa convenção.
- Exclusões de usuários passam por `UsuarioExclusaoService`: remover logicamente perfis, endereços, cartões e históricos de senha; revogar tokens e liberar carrinho/reservas. Pedidos, notas e movimentações permanecem como histórico.
- Relacionamentos históricos podem usar `withTrashed()` para preservar documentos e auditoria. Nunca use isso para autorizar identidades excluídas. Estoque mantém o histórico contábil; correções são feitas por estorno.
- Login é único entre usuários ativos; e-mail é único somente entre contas ativas com e-mail confirmado. Validações devem ignorar excluídos; restauração exige verificar conflitos. Códigos históricos de produtos continuam únicos.
- A cada minuto, `usuarios:limpar-expirados` exclui logicamente contas com e-mail não confirmado, prazo de confirmação vencido e nenhum login registrado. Visitantes não participam dessa limpeza. Reenvio dentro do prazo renova a validade; conta já expirada exige novo cadastro. Confirmação e limpeza usam bloqueio no banco.
- Carrinhos e tokens temporários do Redis continuam sujeitos a TTL e remoção efetiva. Credenciais OAuth são revogadas; tabelas de infraestrutura, sessões, cache e filas não seguem a exclusão lógica de domínio.
- Dados de domínio excluídos logicamente devem ser preservados permanentemente para relatórios futuros. Nunca realizar exclusão física, expurgo ou limpeza desses registros, inclusive contas não confirmadas e históricos de senha. Não adicionar rotinas automáticas de descarte. Relatórios devem incluir registros excluídos explicitamente com `withTrashed()` quando necessário, mantendo autorização e proteção dos dados sensíveis. Essa política não altera o TTL do Redis nem a limpeza de dados temporários de infraestrutura e diagnóstico.
Cadastros pendentes podem compartilhar e-mail. A confirmação verifica a unicidade atomicamente: somente a primeira conta confirmada reserva o endereço. Reenvio por e-mail seleciona o cadastro pendente mais recente ainda válido; recuperação de senha usa somente a conta confirmada.

### Carrinhos abandonados e reservas

- Carrinho visitante: 24 horas sem alterações (`CARRINHO_VISITANTE_TTL=86400`); Cliente: 30 dias (`CARRINHO_CLIENTE_TTL=2592000`).
- Reserva de estoque: 15 minutos após alteração efetiva dos itens (`CARRINHO_RESERVA_TTL=900`). GET, criação de carrinho já existente e reenvio das mesmas quantidades não renovam prazos.
- Após expirar a reserva, os itens permanecem no carrinho até seu próprio TTL. A API inclui `reserva_expira_em` (Unix timestamp) e `reserva_ativa`; o frontend avisa sobre a expiração.
- Checkout revalida preços e saldo descontando reservas de outros carrinhos; reserva vencida não garante estoque. Limpeza e checkout usam o mesmo bloqueio global.
- O scheduler executa `carrinhos:sanitizar` a cada minuto, usa SCAN e libera reservas vencidas, inválidas ou sem item/carrinho correspondente. A adequação inicial infere a idade dos carrinhos antigos pelo TTL restante e aplica os novos prazos sem registrar nova atividade. `CARRINHO_TTL` é mantido como referência do TTL legado durante essa transição.
- Manualmente: `docker compose exec php php artisan carrinhos:sanitizar`. Redis temporário continua fora da política de retenção permanente dos registros PostgreSQL.
### E-mails assíncronos

Confirmação e troca de e-mail, recuperação de senha e alerta de senha antiga usam notificações enfileiradas na conexão Redis, fila `emails`, com supervisor próprio no Horizon. O payload é cifrado; envio ocorre após commit, com três tentativas, backoff de 10/30/60 segundos e timeout SMTP de 20 segundos (`MAIL_TIMEOUT`). Não executar SMTP na requisição HTTP. O Horizon precisa estar ativo.

A validade dos links continua contando desde a solicitação (5 minutos para confirmação e 10 para recuperação no ambiente local); a fila não renova tokens. Antes de enviar, o worker descarta links vencidos/consumidos e notificações de contas excluídas. O texto do e-mail informa que a validade começa na solicitação. Monitorar atrasos da fila, com limiar de 30 segundos; não reenviar jobs com links vencidos como se fossem links novos. Falhas definitivas ficam disponíveis no Horizon para diagnóstico. As respostas HTTP indicam aceitação da solicitação, sem garantir entrega imediata ao destinatário.