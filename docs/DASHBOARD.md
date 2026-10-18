# Painel administrativo

O painel está em `/dashboard/` e o login em `/dashboard/login`, sem cadastro. A interface usa os componentes e a identidade visual da loja, com layout próprio e sem contexto de carrinho.

O guard de sessão `dashboard` é independente de `web`. O login valida senha, e-mail confirmado, ausência de troca inicial obrigatória e perfil administrativo ativo. A proteção revalida perfil e bloqueios em cada acesso; contas e perfis excluídos não autorizam acesso. Tentativas de login têm limite de seis por minuto e escritas exigem CSRF.

## API e OAuth

Depois do login, `POST /dashboard/authorize` inicia Authorization Code + PKCE com o cliente confidencial BFF. A autorização usa `/dashboard/oauth/authorize` com o guard administrativo e callback `/dashboard/callback`. O cliente BFF registra os dois callbacks (loja e painel). Atualize o registro com `docker compose exec php php artisan db:seed --class=BffClientSeeder --force` ao instalar esta versão.

Access/refresh tokens e verifier ficam exclusivamente no servidor; tokens são cifrados em `dashboard.oauth`, separados de `bff`. O JavaScript recebe somente a URL de autorização e o estado da sessão. O proxy `/dashboard/api/*` usa uma lista explícita de produtos, preços, imagens e categorias; encaminha o bearer internamente pelos middlewares originais da API e bloqueia a sessão durante renovação. O proxy da loja continua sem rotas administrativas.

## Pedidos

`/dashboard/pedidos` exige a permissão administrativa `pedidos`. Oferece paginação, filtro por número/status e consulta em `/dashboard/pedidos/{id}`. O operador não cria, edita nem exclui pedidos. Os endpoints `/api/admin/pedidos` e `/api/admin/pedidos/{id}` são somente GET; as ações são POST `/{id}/cancelar`, GET `/{id}/danfe` e GET `/{id}/etiqueta`, com perfil administrativo, escopo `admin:manage` e permissão `pedidos`. O proxy do dashboard permite explicitamente essas operações.

Cancelar preserva o registro e estorna as saídas de estoque com o bloqueio e a transação existentes. Somente pedidos aguardando pagamento, sem qualquer cobrança registrada (inclusive excluída logicamente), podem ser cancelados. Cancelamentos remotos e estornos de pagamentos continuam pendentes; a API responde 409 para pedidos incompatíveis.

A DANFE pode ser baixada para impressão após conclusão fiscal. A etiqueta imprimível é de destinatário, usando o snapshot fiscal do pedido pago, sem CPF/CNPJ; não é uma etiqueta de postagem emitida por transportadora. A página de impressão mostra somente a etiqueta. Não há nova emissão fiscal na requisição administrativa.

Em instalações existentes, provisione inicialmente `pedidos` na lista de permissões do administrador responsável; depois, a gestão de usuários permite que operadores com essa permissão a concedam a outras contas. O seeder do superadministrador de teste inclui essa permissão.

O logout revoga somente os tokens administrativos, remove o guard `dashboard` e regenera sessão e CSRF. O driver `cookie` é incompatível com o BFF. O armazenamento e cookie HttpOnly seguem a configuração existente.

## Usuários

`/dashboard/usuarios` oferece busca por login/e-mail, filtro por cliente/administrador/logística e dez registros por página. `/dashboard/usuarios/novo` cadastra uma conta com o perfil escolhido; `/dashboard/usuarios/{id}/editar` altera login, dados de logística e permissões administrativas, além de formulários separados para senha e solicitação de troca de e-mail. A mudança de perfil de uma conta existente não faz parte deste fluxo. Contas temporárias de visitante e contas excluídas não entram nesta listagem.

Os endpoints `/api/admin/usuarios` (GET/POST), `/api/admin/usuarios/{id}` (GET/PATCH/DELETE) e `/api/admin/usuarios/{id}/email` (POST) exigem `admin:manage`, perfil administrativo e a permissão específica `usuarios`. O BFF expõe somente esses caminhos, com CSRF nas escritas; o proxy seleciona a permissão por módulo e preserva a verificação da API. Rotas de autoatendimento do cliente continuam exigindo propriedade da conta.

Novos cadastros passam pelo serviço existente e confirmação de e-mail assíncrona. E-mails não são alterados por PATCH e a solicitação de troca só é aplicada após confirmação e nova senha. Senhas respeitam hash, pepper e histórico existentes. Alterar senha ou permissões revoga os tokens e exige novo login. Não são expostos hashes, credenciais, endereços, cartões ou dados fiscais neste CRUD.

Não é permitido conceder permissões que o operador não possui, modificar/excluir administradores com permissões além das próprias, excluir a própria conta ou retirar de si a permissão de usuários. Exclusão usa `UsuarioExclusaoService`, preservando histórico e revogando acessos. A conta `superadmin-teste` recebe `usuarios` no seeder para testar o módulo; contas existentes precisam receber essa permissão explicitamente. Não é necessário mudar o esquema do banco.

## Catálogo de produtos

`/dashboard/produtos` lista dez produtos por página, com busca por nome/código, edição e exclusão lógica. Inclui inativos e produtos sem estoque. Os endpoints públicos continuam exibindo apenas o catálogo disponível. As páginas e a API administrativa exigem permissão `estoque`, além do perfil administrativo e escopo OAuth `admin:manage`.

`/dashboard/produtos/novo` cadastra código, nome, categoria, descrição, preço inicial e status. Depois de salvar, abre a edição para adicionar fotos. `/dashboard/produtos/{id}/editar` permite alterar dados e status, registrar um novo preço preservando histórico e gerenciar fotos (URL pública, prévia, edição, principal e remoção lógica). A área de fotos também aceita arquivos por seleção, arrastar e soltar ou colagem da área de transferência (Ctrl+V/⌘V com a área focada). O hook `useUploadImagens` mantém a seleção e libera as URLs de prévia ao remover arquivos ou sair da tela. O envio é sequencial: fotos já salvas saem da seleção; falhas preservam as restantes para nova tentativa. Configurações fiscais existentes são preservadas ao editar.

Novas leituras protegidas: `GET /api/admin/produtos` (paginação e busca) e `GET /api/admin/produtos/{id}`. Escritas e imagens reutilizam os endpoints existentes. Todo acesso pelo navegador passa por `/dashboard/api` e mantém tokens fora do navegador. Formulários exibem erros da API e usam a cobertura compartilhada de processamento.

Endpoints de sessão: `POST /dashboard/login`, `GET /dashboard/session`, `POST /dashboard/logout`. A consulta retorna `login`, `paginas` e `api_autorizada`, sem credenciais. Obtenha CSRF em `GET /oauth/csrf` e envie `X-CSRF-TOKEN` nas escritas. Após a autorização OAuth, obtenha novamente o CSRF no Postman; a sessão é regenerada. A coleção contém o fluxo e as leituras administrativas.

## Upload de fotos

POST /api/admin/produtos/{id}/imagens/upload aceita multipart/form-data com arquivo e principal opcional (0/1), mantendo escopo admin:manage e permissão estoque. O navegador usa /dashboard/api/admin/produtos/{id}/imagens/upload com cookie e CSRF; os tokens continuam no servidor. Formatos: JPEG, PNG e WebP, até 5 MB por arquivo e 6000 × 6000 pixels. A interface seleciona até dez arquivos por vez. O cadastro por URL continua disponível como alternativa.

Os arquivos recebem nomes aleatórios no disco public, em produtos/{id}, e o registro guarda a URL pública. Exclusões lógicas preservam os arquivos. Somente uploads cujo cadastro falhou são desfeitos. Na instalação, execute docker compose exec php php artisan storage:link. O Nginx aceita corpo de até 6 MB para acomodar um arquivo e o multipart; o PHP existente já permite 20 MB. Alterações do Nginx requerem nginx -t seguido de nginx -s reload no container.
