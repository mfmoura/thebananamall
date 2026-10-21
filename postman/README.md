# Testes manuais no Postman

Cronômetro de pagamento: `GET /pedidos/{pedidoId}/pagamento` inclui `instrucoes.criado_em` e `instrucoes.expira_em` quando retornadas pelo provedor (datas ISO 8601). O detalhe do pedido no frontend consulta esse endpoint para obter os prazos; o resumo da listagem permanece inalterado. O frontend atualiza a contagem e a barra a cada segundo com o vencimento absoluto, sem reiniciar ao recarregar. Sem vencimento, mostra o tempo de espera desde a criação; chegar a zero não confirma nem cancela o pagamento. O prazo aparece após a conciliação pela fila `pagamentos`.

O catálogo aceita `categoria` (nome_codigo de uma categoria ativa; inicialmente bandeira, botton, camiseta e outro) e `busca` (trecho do nome, código ou descrição, sem distinguir maiúsculas). Os filtros podem ser combinados e são aplicados antes da paginação. Na loja React, a categoria é aplicada imediatamente e a busca após 1 segundo sem digitação.

1. Inicie os containers: `docker compose up -d`.
2. Aplique migrations e seeders: `docker compose exec php php artisan migrate --seed`.
3. Importe `LojaVirtualLCB.postman_collection.json` no Postman.
4. Configure `baseUrl` (padrÃ£o `http://localhost/api`).

## OAuth2 e tokens

O cadastro e as trocas de senha aceitam de **8 a 15 caracteres**, com confirmação. Senhas antigas continuam sendo aceitas no login. Na loja React, o formulário de cadastro fica centralizado e, após sucesso na API, redireciona para `/email/pendente`, onde é possível reenviar a confirmação de e-mail.

Produtos aceitam `descricao` no cadastro e na atualização (texto opcional, até 10000 caracteres; `null` remove o texto). O campo é retornado nas consultas e exibido no detalhe React. A pasta **Descricao de produtos** traz atualização e consulta. O seeder `ProdutoDescricaoSeeder` preenche somente descrições vazias dos registros existentes.

**BFF:** a loja React agora usa sessão HttpOnly, e access/refresh tokens permanecem no servidor. Use a pasta **BFF - sessao HttpOnly** e `bffUrl`; preserve os cookies e informe `csrfToken` nas escritas. Não copie tokens OAuth do navegador: o BFF não os devolve. O antigo cliente público da SPA foi revogado; integrações que chamam `/api/*` diretamente precisam de seu próprio cliente OAuth autorizado. Consulte [docs/BFF.md](../docs/BFF.md) para instalação, autorização e renovação. O callback confidencial é `/bff/callback`; `/oauth/callback` é apenas a página React após a conclusão no servidor.

A pasta **Catalogo paginado e galeria** solicita páginas de cinco produtos. A resposta paginada fica em `data.data`, com `data.current_page` e `data.next_page_url`; sem `per_page`, o endpoint preserva a lista completa. Cada produto inclui sua imagem principal e as adicionais. A primeira imagem cadastrada torna-se principal automaticamente; ao excluir a principal, outra é promovida. Para trocar a principal explicitamente, atualize a imagem desejada com `principal: true` nas requisições administrativas de imagens já existentes.

A pasta **Frontend React - sessao e checkout** documenta as rotas REST originais para integrações. Na loja, use o BFF com cookies e CSRF. O callback OAuth confidencial é http://localhost/bff/callback; a porta 5173 serve somente assets.

O cliente público antigo 00000000-0000-4000-8000-000000000001 está revogado após a migração BFF. Para chamadas REST externas, cadastre um cliente OAuth próprio com callback e escopos apropriados; a loja usa o cliente confidencial do servidor.

Para endpoints REST protegidos, configure clienteToken ou adminToken usando um cliente OAuth próprio de integração. O BFF não expõe tokens para copiar. Pedidos exigem orders:write e perfil Cliente; operações administrativas exigem admin:manage e perfil Administrador.

Administradores armazenam `paginas` como array JSON com valores `contabilidade`, `estoque` e `manutencao`. Operacoes de produtos exigem a pagina `estoque`; qualquer administrador pode criar perfis Administrador e Logistica usando o escopo `admin:manage`, mas so pode conceder paginas que possui.

## Visitante e carrinho

Execute **0. Inicializar identidade de visitante**. A resposta retorna uma credencial temporÃ¡ria, salva automaticamente em `visitanteToken`. Use-a no header `X-Visitante-Token` para as operaÃ§Ãµes de carrinho deslogado. O visitante e seu usuÃ¡rio tÃ©cnico sÃ£o persistidos no PostgreSQL; o carrinho permanece no Redis e expira conforme `CARRINHO_TTL`.

ApÃ³s entrar pelo frontend e obter token de Cliente, execute **8. Mesclar carrinho apÃ³s OAuth login**. A operaÃ§Ã£o soma as quantidades do carrinho de visitante ao carrinho ativo do Cliente e invalida a credencial de visitante. O endpoint de compra converte o carrinho em pedido e requer Cliente autenticado.

## Produtos e estoque

A listagem de produtos, consulta de preÃ§os e consulta de estoque sÃ£o pÃºblicas. O campo `estoque` representa a disponibilidade (estoque contado menos reservas ativas); a consulta individual tambÃ©m retorna `estoque_contado` e `estoque_disponivel`. Carrinhos sem estoque suficiente recebem erro de validaÃ§Ã£o. OperaÃ§Ãµes de escrita exigem token de Administrador com escopo `admin:manage`. Os seeders criam produtos de demonstraÃ§Ã£o da LCB. As movimentaÃ§Ãµes exigem quantidade positiva e referÃªncia de nota; saÃ­das manuais sÃ³ usam saldo nÃ£o reservado. A exclusÃ£o de movimentaÃ§Ã£o Ã© um estorno auditÃ¡vel e a exclusÃ£o de produto o desativa para preservar o histÃ³rico.

O campo `codigo` Ã© o SKU textual; o `id` numÃ©rico Ã© usado pelo carrinho e pelas referÃªncias internas. PreÃ§os sÃ£o histÃ³ricos: o checkout captura o preÃ§o vigente no servidor. A collection contÃ©m chamadas para consultar o histÃ³rico, registrar novo preÃ§o e excluir preÃ§o antigo sem referÃªncias.

## ConfirmaÃ§Ã£o de e-mail e primeira senha do Administrador

O cadastro envia um link ao e-mail informado. A collection possui `emailVerificationToken` para testar manualmente `POST /api/email/confirmar`; o endpoint de reenvio Ã© `POST /api/email/reenviar-confirmacao`. No frontend, o link abre `/email/verify?token=...`; para uma troca de e-mail, a tela tambÃ©m solicita uma senha nova e envia `password` e `password_confirmation` ao endpoint de confirmaÃ§Ã£o.

Antes de executar o cadastro na collection, preencha `emailTesteBase` com uma caixa que vocÃª controla. O script acrescenta um alias Ãºnico para cada execuÃ§Ã£o, para que a confirmaÃ§Ã£o chegue nessa caixa sem repetir um endereÃ§o jÃ¡ cadastrado.

Para trocar o endereÃ§o autenticado, envie `POST /api/usuarios/{id}/email` com `email`. O novo endereÃ§o e o token ficam temporariamente no Redis, com duraÃ§Ã£o de `EMAIL_VERIFICATION_TTL` (300 segundos no `.env` local). O endereÃ§o atual sÃ³ muda quando o link Ã© confirmado. Ao confirmar a troca, os tokens OAuth sÃ£o revogados e o usuÃ¡rio precisa entrar novamente.

O Administrador inicial usa `ADMIN_EMAIL` e `ADMIN_INITIAL_PASSWORD` do `.env` local. Confirme o endereÃ§o primeiro; o primeiro pedido de login retorna `password_change_required`. O frontend deve enviar a senha temporÃ¡ria e a nova senha a `POST /oauth/first-login/password`, e entÃ£o iniciar um novo login. Consulte `ADMIN_INITIAL_PASSWORD` localmente; nÃ£o compartilhe esse valor.

## Contas de teste

As contas de demonstraÃ§Ã£o usam `Senha@12345`; `admin-teste` Ã© o Administrador inicial e usa `ADMIN_INITIAL_PASSWORD` atÃ© a troca obrigatÃ³ria.

| Perfil | Login | E-mail |
|---|---|---|
| Cliente | `cliente-teste` | `cliente-teste@example.test` |
| Administrador (Contabilidade e Estoque) | `admin-teste` | valor de `ADMIN_EMAIL` no `.env` |
| Administrador (Contabilidade) | `superadmin-teste` | `superadmin-teste@example.test` |
| LogÃ­stica | `logistica-teste` | `logistica-teste@example.test` |

O CRUD de Logistica agora esta disponivel em `/api/admin/logisticas`; o perfil atende apenas integracoes operacionais futuras.

ApÃ³s `migrate --seed`, a forma de pagamento com ID 1 Ã© PIX e os produtos de demonstraÃ§Ã£o usam IDs 1 e 2 em um banco vazio. Confira os IDs retornados se o banco jÃ¡ tiver cadastros.
## CRUD de Cliente, Administrador, Logistica e Endereco

A collection inclui chamadas separadas de criar, consultar, atualizar e excluir para cada perfil e para enderecos.

- `POST /clientes` e publico e sempre cria um Cliente. A verificacao do email segue o fluxo normal.
- `GET/PUT/DELETE /clientes/{id}` exigem OAuth2 e permitem o proprietario ou qualquer Administrador.
- `POST/GET/PUT/DELETE /admin/administradores` e `/admin/logisticas` exigem `admin:manage`. Somente Administradores podem criar esses perfis.
- `POST/GET/PUT/DELETE /enderecos` exige OAuth2. O proprietario ou qualquer Administrador pode gerenciar o endereco; a API ViaCEP confirma CEP, cidade e UF.

Os testes de cada chamada POST salvam automaticamente os IDs retornados: `clienteId` e `clienteProfileId`, `administradorId` e `administradorProfileId`, `logisticaId` e `logisticaProfileId`, alem de `enderecoId`. Use os IDs de perfil nas rotas CRUD; `clienteId` e o ID da conta Usuario usado ao criar endereco para outra pessoa como Administrador. Para trocar email, use o endpoint dedicado `/usuarios/{id}/email`; os endpoints de perfil nao alteram email diretamente.

Na criacao de Administrador, informe em `paginas` apenas valores que o token Administrador atual possui: `contabilidade`, `estoque` ou `manutencao`.

## Envelope das respostas

Todas as respostas REST em `/api/*` usam `status` (`success` ou `error`), `statusCode` (igual ao status HTTP) e `data`. Os scripts desta collection leem os retornos de recurso em `pm.response.json().data`. Exclusões retornam HTTP 200 com `data: null` para preservar o envelope.

## Recuperação de senha

Defina `passwordRecoveryEmail` e execute `POST /api/senha/recuperar`. Se a conta existir, o link será enviado para esse endereço. No ambiente local com `MAIL_MAILER=log`, consulte o log de e-mail do Laravel e copie o token do link para `passwordRecoveryToken`; defina `passwordRecoveryNewPassword` e execute `POST /api/senha/redefinir`. O token expira conforme `PASSWORD_RECOVERY_TTL` (600 segundos no `.env` local) e só pode ser usado uma vez.

## Notas fiscais e ajustes de estoque

No checkout de Cliente, envie `dados_fiscais` (nome, CPF/CNPJ, indicador de IE e endereço fiscal), conforme o exemplo da collection. A resposta traz `processamento_fiscal` com mensagem de processamento; nenhuma emissão é feita dentro da requisição. A fila Redis `fiscal`, gerenciada pelo Horizon, começa a emissão depois da confirmação do pagamento pelo backend. Consulte o pedido e use **Baixar meu DANFE apos processamento** quando estiver pronto. Não há chamada pública para confirmar pagamento. A pasta **Filas (Horizon)** usa `horizonUrl` e `adminToken` de Manutenção para consultar as estatísticas.

Os exemplos de criação/atualização de produto incluem `configuracao_fiscal`. Configure também UF do emitente, CNPJ, série e token de homologação no backend. O Docker Compose inicia `horizon` e `scheduler`; o scheduler recupera pendências de publicação da fila.

A pasta **NFE.io (homologacao)** inclui emissão, consulta local, sincronização e downloads XML/DANFE. Configure `NFE_IO_TOKEN`, `NFE_IO_COMPANY_ID`, `NFE_IO_STATE_TAX_ID`, CNPJ, UF e série no backend; a inscrição deve ser NF-e `Test` e ter certificado A1. Configure o host exato do XML em `NFE_IO_DOWNLOAD_HOSTS`. Na collection use `adminToken` (Contabilidade, `admin:manage`), `produtoId` e `nfeIoIdempotencyKey`. A resposta salva `notaFiscalNfeIoId`. A chave deduplica localmente: após timeout sem ID remoto, a repetição retorna 409 e exige reconciliação no painel, sem outro POST na NFE.io. Os downloads retornam arquivos privados; Logística usa `logisticaToken` com `logistics:manage`. Homologação não movimenta estoque. Guia: [NFE.io](../docs/FISCAL-NFE-IO.md).

- `POST /api/admin/notas-fiscais` exige Administrador com pagina `contabilidade`. Registra documento fiscal autorizado com natureza `compra`, `remessa`, `devolucao`, `doacao`, `venda` ou `perda`; cada item cria a movimentacao de estoque correspondente.
- Uma nota de venda vinculada a `pedido_id` valida os itens contra o pedido e nao baixa estoque novamente, pois o checkout ja consumiu a reserva. Venda sem pedido, doacao e perda baixam o estoque disponivel, descontadas as reservas ativas no Redis.
- `POST /api/admin/estoque/movimentacoes` aceita ajustes de `entrada` e `saida` para Estoque ou Manutenção (`tipo=ajuste`, padrão), com status Ajuste de Estoque. Contabilidade usa `tipo=nota`, somente `entrada`, com `nota` obrigatória e status Nota a verificar; por enquanto não há verificação externa.
- A collection inclui exemplos de registro de nota de compra e ajuste de entrada. Configure `adminToken` com escopo `admin:manage` e as permissoes da pagina correspondente.

### Exclusão lógica e confirmação de cadastro

Os dados de domínio no PostgreSQL usam `deleted_at`. Endpoints de exclusão mantêm o formato de resposta e deixam de retornar registros excluídos. Login pode ser reutilizado após exclusão da conta. E-mails não são reservados por cadastros pendentes; somente contas ativas confirmadas exigem unicidade. Pedidos e documentos históricos são preservados.

Contas não confirmadas, sem login e com prazo de confirmação vencido são excluídas logicamente pelo scheduler a cada minuto (`php artisan usuarios:limpar-expirados`). Visitantes são isentos. Reenvio antes do vencimento renova o prazo; após o vencimento é necessário refazer o cadastro. Carrinhos Redis continuam com TTL e são liberados ao excluir a conta.
Cadastros pendentes podem compartilhar e-mail. A confirmação verifica a unicidade atomicamente: somente a primeira conta confirmada reserva o endereço. Reenvio por e-mail seleciona o cadastro pendente mais recente ainda válido; recuperação de senha usa somente a conta confirmada.

### Carrinhos abandonados e reservas

- Carrinho visitante: 24 horas sem alterações (`CARRINHO_VISITANTE_TTL=86400`); Cliente: 30 dias (`CARRINHO_CLIENTE_TTL=2592000`).
- Reserva de estoque: 15 minutos após alteração efetiva dos itens (`CARRINHO_RESERVA_TTL=900`). GET, criação de carrinho já existente e reenvio das mesmas quantidades não renovam prazos.
- Após expirar a reserva, os itens permanecem no carrinho até seu próprio TTL. A API inclui `reserva_expira_em` (Unix timestamp) e `reserva_ativa`; o frontend avisa sobre a expiração.
- Checkout revalida preços e saldo descontando reservas de outros carrinhos; reserva vencida não garante estoque. Limpeza e checkout usam o mesmo bloqueio global.
- O scheduler executa `carrinhos:sanitizar` a cada minuto, usa SCAN e libera reservas vencidas, inválidas ou sem item/carrinho correspondente. A adequação inicial infere a idade dos carrinhos antigos pelo TTL restante e aplica os novos prazos sem registrar nova atividade. `CARRINHO_TTL` é mantido como referência do TTL legado durante essa transição.
- Manualmente: `docker compose exec php php artisan carrinhos:sanitizar`. Redis temporário continua fora da política de retenção permanente dos registros PostgreSQL.
A política de senhas usa validação local e histórico de hashes; cadastro e troca de senha não consultam mais a API Pwned Passwords.

Se o login responder 200 e a autorização OAuth falhar, confira as permissões das chaves e o registro da resposta de consentimento Passport em [docs/BFF.md](../docs/BFF.md). Não regenere chaves para corrigir acesso a arquivos.

### E-mails assíncronos

Confirmação e troca de e-mail, recuperação de senha e alerta de senha antiga usam notificações enfileiradas na conexão Redis, fila `emails`, com supervisor próprio no Horizon. O payload é cifrado; envio ocorre após commit, com três tentativas, backoff de 10/30/60 segundos e timeout SMTP de 20 segundos (`MAIL_TIMEOUT`). Não executar SMTP na requisição HTTP. O Horizon precisa estar ativo.

A validade dos links continua contando desde a solicitação (5 minutos para confirmação e 10 para recuperação no ambiente local); a fila não renova tokens. Antes de enviar, o worker descarta links vencidos/consumidos e notificações de contas excluídas. O texto do e-mail informa que a validade começa na solicitação. Monitorar atrasos da fila, com limiar de 30 segundos; não reenviar jobs com links vencidos como se fossem links novos. Falhas definitivas ficam disponíveis no Horizon para diagnóstico. As respostas HTTP indicam aceitação da solicitação, sem garantir entrega imediata ao destinatário.
Em `/conta`, o formulário de endereço consulta o ViaCEP ao completar o CEP (oito dígitos, com ou sem hífen). Preenche logradouro, cidade, UF e código IBGE; número e complemento permanecem manuais. O React usa `GET /bff/api/enderecos/cep/{cep}`; integrações usam `GET /api/enderecos/cep/{cep}` com OAuth. A resposta inclui `bairro` e `codigo_municipio`, usados pelo checkout para montar os dados fiscais sem pedir o código IBGE ao Cliente. Requer conta liberada, limita a 30 consultas por minuto e cancela respostas de CEPs anteriores. A consulta não salva o endereço nem substitui a validação no servidor.

Na listagem pública do catálogo, produtos sem estoque disponível (estoque contado menos reservas ativas) são omitidos antes da paginação. A consulta de detalhe continua disponível para produtos sem estoque e para referências históricas de pedidos. A loja carrega cinco produtos por página.

No React, a compra e o seletor de quantidade aparecem apenas no detalhe do produto. O carrinho apresenta nome e miniatura principal; na tela do pedido, cada item abre o detalhe do produto mesmo quando seu estoque está zerado. O checkout usa ViaCEP para obter o código IBGE, aplica máscaras visuais de CPF/CNPJ e CEP e, quando há endereço salvo, pede confirmação do cartão do endereço antes de criar o pedido.

Após o primeiro pedido, o Cliente mantém um perfil fiscal cifrado com a chave da aplicação no campo `clientes.dados_fiscais`. O checkout seguinte mostra os dados mascarados em um cartão e pede confirmação; nomes, documentos e endereços não são devolvidos em rotas públicas.

## Mercado Pago

O checkout agora segue de `/checkout` para `/checkout/pagamento/{pedidoId}` após persistir o pedido. A etapa mostra Pix (QR Code/copia e cola), campos seguros MercadoPago.js para crédito/débito à vista e acompanhamento por consulta ao servidor. `GET /pagamentos/configuracao`, com Cliente e `orders:write`, retorna somente `public_key` e `parcelas=1`, sem cache. Pelo navegador, o endpoint passa pelo BFF. As requisições de configuração foram adicionadas à pasta Mercado Pago. Cartões aceitam somente `parcelas: 1`; PAN/CVV e valor enviado pelo cliente são rejeitados. Não salve tokens de cartão na coleção. Homologação com comprador de teste e revisão de produção continuam pendentes.

A pasta Mercado Pago (Orders) cria Pix/cartão tokenizado e consulta o estado local da cobrança de pedido próprio com escopo orders:write. Use pedidoId e clienteToken do fluxo OAuth existente. Credenciais do provedor ficam somente no .env. Configure o webhook pelo painel, com assinatura válida; veja [docs/MERCADO-PAGO.md](../docs/MERCADO-PAGO.md). O cancelamento local retorna 409 depois de iniciar uma tentativa de cobrança. Estornos remotos e novas tentativas após rejeição ainda não fazem parte deste fluxo.

Categorias: GET /categorias é público e retorna id, nome_codigo e nome. Use nome na exibição e nome_codigo no campo/filtro categoria dos produtos. Produtos incluem categoria_cadastro com os dados da categoria. Categorias iniciais: Bandeira, Botton, Camiseta e Outro. Novas categorias ativas também são aceitas; categorias excluídas são rejeitadas na validação.

## Dashboard administrativo

A pasta **Dashboard - usuários** contém o CRUD administrativo (`/admin/usuarios`), com `adminToken`, escopo `admin:manage` e página `usuarios`. Use `usuarioAdminId` para consulta, edição, senha, solicitação de troca de e-mail e exclusão lógica. A listagem aceita `page`, `per_page` (até 50), `busca` (login/e-mail) e `tipo` (`cliente`, `administrador`, `logistica`). Administradores recebem `paginas` e logística recebe `nome` e `webhook`; tipo não pode ser trocado em uma conta existente. Novos cadastros precisam confirmar e-mail. A troca de e-mail usa confirmação assíncrona, sem alteração direta. Senhas/permissões alteradas revogam tokens. O mesmo fluxo pelo navegador usa `/dashboard/api/admin/usuarios`, cookie, CSRF e a autorização PKCE existente. O retorno de `/dashboard/session` inclui também o identificador `id` da conta administrativa. A própria conta não pode ser excluída, e não é possível conceder ou administrar permissões além das próprias.

Upload de fotos: `POST /admin/produtos/{produtoId}/imagens/upload`, ou `/dashboard/api/admin/produtos/{produtoId}/imagens/upload` pelo BFF. Use multipart/form-data com `arquivo` (JPEG, PNG ou WebP, até 5 MB e 6000×6000 pixels) e `principal` opcional (`0` ou `1`). As requisições estão na pasta de catálogo administrativo. Não defina Content-Type manualmente: o Postman deve gerar o boundary. O BFF exige cookie, CSRF e autorização PKCE; a API direta exige `adminToken` com `admin:manage` e página `estoque`. Retorno 201 inclui a URL pública. Remover a foto exclui logicamente o registro e preserva o arquivo.

A pasta **Dashboard - sessão administrativa** testa o login exclusivo de administradores, consulta de sessão e logout por cookie, sem bearer token. Execute a obtenção de CSRF antes das escritas e mantenha o cookie jar habilitado. Use `webUrl` para a origem da aplicação, `adminLogin` e `adminPassword`; a conta precisa ter perfil administrativo ativo, e-mail confirmado e senha inicial já trocada. A interface fica em `/dashboard/`, sem cadastro. Consulte `docs/DASHBOARD.md`.

A pasta **Dashboard - catálogo administrativo** adiciona autorização PKCE por cookie e leituras `GET /api/admin/produtos` e `GET /api/admin/produtos/{id}`, com bearer `adminToken` para integrações diretas. Estas leituras incluem produtos inativos e sem estoque e exigem `admin:manage` e página `estoque`. Paginação: `page` e `per_page` (1 a 50), com busca `busca` e filtro `categoria`. No navegador, o CRUD usa `/dashboard/api/admin/produtos`, reutilizando as escritas existentes de produtos, preços e imagens. Fotos são URLs públicas; é possível editar, definir principal e remover. Após concluir a autorização, obtenha novamente o CSRF antes das escritas. O cadastro usa código único; repeti-lo sem alterar o código resulta em validação 422.

Pagamento assíncrono: `POST /pedidos/{pedidoId}/pagamento` retorna **202 Accepted**, persistindo a solicitação antes de publicar o job Redis `pagamentos`. `GET` lê somente o banco, sem aguardar o Mercado Pago; consulte `processamento` (`pendente`, `processando`, `aguardando`, `concluido`, `verificacao_necessaria`), `status` remoto e instruções Pix. `GET /pedidos` e `GET /pedidos/{id}` incluem um resumo `pagamento` sem segredos. A fila usa a mesma chave e snapshot em retentativas; não envie outro cartão para um resultado incerto. O token temporário fica cifrado e oculto até obter a Order e então é apagado. Horizon e scheduler precisam estar ativos; `pagamentos:retomar` recupera pendências. O webhook continua validando e consultando o recurso assinado.

## Boleto no checkout

Após a confirmação verificada do pagamento, qualquer modalidade (Pix, boleto, crédito ou débito) enfileira um e-mail ao Cliente na conexão Redis, fila `emails`, após commit. A mensagem contém o número e o link do pedido; notificações repetidas do provedor não enfileiram outra confirmação para o mesmo pedido. Pendência e recusa não enviam esse e-mail. O payload é cifrado e o Horizon precisa estar ativo; contas excluídas são descartadas antes do envio. A tela acompanha o pagamento automaticamente, sem botão de atualização manual.

Recusas de cartão que retornam HTTP 402 do Mercado Pago com Order válida são conciliadas pelo worker: o ID é preservado, o recurso é consultado e o GET local passa a informar `status=failed` e `processamento=concluido`. A solicitação inicial da loja continua retornando 202. Falha na consulta mantém a mesma Order para retomada, sem reenviar o token. Resultado incerto não autoriza nova cobrança.

Para pedido com forma `boleto`, envie `POST {{baseUrl}}/pedidos/{{pedidoId}}/pagamento` com `{"meio":"boleto"}`, bearer `clienteToken` e escopo `orders:write`. O pedido precisa ter snapshot fiscal completo, incluindo nome completo/razão social, CPF/CNPJ e endereço. Retorna 202 e processa na fila Redis `pagamentos`. Consulte GET no mesmo endereço até obter `instrucoes.digitable_line`, `barcode_content` e `ticket_url`; abra o link para conferir o vencimento e imprimir. Geração não significa pagamento confirmado. Pelo navegador, use o mesmo fluxo no BFF com cookie/CSRF; nenhum token de cartão é necessário.

## Pedidos no dashboard

Em `/dashboard/pedidos`, administradores com a permissão `pedidos` podem listar, filtrar por número/status, consultar e executar ações. Conceda essa permissão pela gestão de usuários usando uma conta que já a possua; instalações existentes precisam provisioná-la inicialmente por um administrador responsável no servidor. Não há criação, edição ou exclusão de pedidos nesse painel.

A pasta **Dashboard - Pedidos (consulta e acoes)** usa `baseUrl`, `pedidoId` e `adminToken` (token OAuth administrativo com escopo `admin:manage`). Cancelamento usa POST sem payload, preserva o histórico e estorna o estoque. Somente pedidos aguardando pagamento e sem qualquer cobrança criada podem ser cancelados; os demais retornam 409, até existir suporte a cancelamento/estorno no provedor.

DANFE retorna PDF quando disponível. A etiqueta retorna nome e endereço do snapshot fiscal do pedido pago para impressão; não é uma etiqueta de postagem de transportadora. No navegador, o painel usa sessão HttpOnly e CSRF pelo proxy `/dashboard/api/admin/pedidos`, sem expor tokens. Somente as ações explícitas são permitidas pelo proxy.

Validação de Webhooks Orders: assinatura inválida ou ausente retorna 401 sem persistência. O receptor valida HMAC-SHA256 localmente com hash_hmac/hash_equals, sem SDK. Verifica manifest com ID em minúsculas e, por compatibilidade comprovada com o simulador, com o ID original; ambos exigem HMAC válido. O request-id vem de x-request-id e o ts é extraído de x-signature. ID e x-request-id são obrigatórios. HTTP 200 confirma o registro durável em mercado_pago_eventos; consulta e conciliação pelo ID original ocorrem depois na fila Redis pagamentos. Configure Horizon e scheduler; webhooks:retomar recupera publicação após falha do Redis. Notificações duplicadas com os mesmos campos assinados reutilizam o evento. Consulte docs/MERCADO-PAGO.md para migration e retentativas.


Expedição: use a requisição 'Expedição - pedidos pagos aguardando entrega', com adminToken, escopo admin:manage e permissão pedidos. expedicao=1 restringe a listagem a pedidos pagos/em_processamento, com nota autorizada e processamento fiscal concluído, ainda não enviados. Consulte o detalhe administrativo para disponibilidade de DANFE e etiqueta de destinatário. Etiqueta de postagem e webhook logístico ainda dependem do provedor de frete para loja própria.


## Cotação de frete integrada

Cadastre peso_gramas, altura_cm, largura_cm e comprimento_cm nos produtos; a requisição 'Configurar embalagem do produto (Admin)' demonstra o payload. Use 'Cotar frete Hub (Cliente)' com um carrinho não vazio e clienteToken com orders:write. A consulta salva freteCotacaoId e freteServico na coleção. A criação de pedido agora exige essas variáveis; escolha somente códigos presentes na resposta e recote após cinco minutos ou mudança dos itens/destino. Não envie custo_frete nem token do Hub. O frontend usa o BFF com cookie e CSRF. Veja [Frete](../docs/FRETE.md).

### Logística por produto
A pasta Logística por produto lista e pesquisa produtos, consulta saldo e histórico paginado por produto_id (page/per_page, máximo 50), e registra movimentações com adminToken/admin:manage. Estoque e Manutenção usam tipo=ajuste e direcao=entrada ou saida; nota é opcional e o status é Ajuste de Estoque. Contabilidade usa tipo=nota, direcao=entrada e nota obrigatória (referência fictícia, sem consulta externa), status Nota a verificar. Perfis acumulados podem escolher o tipo; a API valida a permissão correspondente. Saídas respeitam reservas e saldo disponível. Registros anteriores permanecem no histórico.

O reenvio de confirmação (POST /api/email/reenviar-confirmacao) também aceita administradores com prazo anterior vencido, gerando um novo link com validade contada desde a solicitação. Contas comuns expiradas continuam exigindo novo cadastro. Na loja, login pendente informa a necessidade de confirmação; use Reenviar confirmação. No painel, POST /dashboard/login com credenciais corretas e perfil administrativo ativo solicita automaticamente confirmação pela fila emails, no máximo uma vez por minuto por conta, e retorna 403 sem autenticar. HTTP 202 indica aceitação, sem garantir entrega.

Usuários não administradores: consulta disponível aos perfis administrativos; criação, edição, troca de e-mail e exclusão exigem a página `manutencao`. O painel apresenta Visualizar aos demais perfis. Manutenção também cria e edita administradores e pode conceder qualquer permissão administrativa, inclusive as que não possui, e editar administradores com outras permissões. Operadores somente com `usuarios` continuam limitados às próprias permissões. Exclusão de administradores exige `usuarios` e permissões compatíveis.

## Cadastro de categorias

GET /api/categorias lista categorias ativas. A pasta Cadastro de categorias oferece POST /api/admin/categorias, PATCH e DELETE /api/admin/categorias/{categoriaId}, com Bearer adminToken, escopo admin:manage e permissão estoque. Copie o id retornado no cadastro para categoriaId. O cadastro recebe nome_codigo (até 40 caracteres: letras minúsculas, números, hífen ou sublinhado) e nome (até 255 caracteres). PATCH altera somente nome; nome_codigo é imutável e não pode ser reutilizado após exclusão. DELETE é lógico e preserva os vínculos existentes. Produtos recebem categoria com o nome_codigo de uma categoria ativa.

Confirmação administrativa: obtenha CSRF em GET /oauth/csrf, preserve o cookie e execute POST /dashboard/email/confirmar com token do link administrativo (variável emailVerificationToken). Para troca de e-mail, inclua password e password_confirmation. A resposta requires_password_change indica continuação em /dashboard/senha-obrigatoria. /api/email/confirmar aceita somente contas sem perfil administrativo. Novos links administrativos apontam para /dashboard/email/verify; reenvio disponível em /dashboard/email/pendente.

## Grades de tamanhos

A pasta **Grade de tamanhos** cadastra um produto com grade, registra entrada por tamanho e exercita inclusão, alteração, substituição e remoção no carrinho. Usa `adminToken` (admin:manage e página estoque), `clienteToken` (cart:read/cart:write), `produtoGradeId` preenchido pelo cadastro e `tamanhoGrade` (inicialmente M). Cadastre saldo em P e M antes do exemplo de substituição. O checkout existente preserva o tamanho; peso, medidas e cotação de frete continuam necessários. Veja [Grades de tamanhos](../docs/GRADES.md).
## Acesso de administradores à loja

Na edição administrativa, `PATCH /api/admin/usuarios/{usuario}` aceita `acesso_loja` booleano somente para contas com perfil Administrador. A requisição **Habilitar acesso à loja para administrador** usa `usuarioAdminId`, o token administrativo com `admin:manage` e as mesmas permissões de edição da conta. Envie `true` para habilitar o perfil Cliente (carrinho e compras) ou `false` para desativá-lo, liberar reservas e remover o carrinho. A desativação é lógica; reativar restaura o mesmo perfil e preserva os documentos históricos. Mudanças efetivas revogam os tokens e exigem novo login. Reenviar o mesmo valor não revoga tokens. No navegador, use o proxy `/dashboard/api/admin/usuarios/{usuario}` com cookie HttpOnly e CSRF.
