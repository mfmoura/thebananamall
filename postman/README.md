# Testes manuais no Postman

1. Inicie o cluster e aplique as migrations e os usuários de teste: `docker compose up -d` e `docker compose exec php php artisan migrate --seed`.
2. Importe `LojaVirtualLCB.postman_collection.json` no Postman.
3. Rode as requisições na ordem numérica da pasta **Autenticação e Cliente**. O cadastro cria valores únicos e salva `clienteId`; o login salva `clienteToken` automaticamente.
4. A URL padrão é `http://localhost/api`. Se o Laravel estiver em outro host/porta, altere a variável de coleção `baseUrl`.
5. Para testar o carrinho, faça login como Cliente e use a pasta **Carrinho Redis**. O seeder cria dois produtos de demonstração. Depois, use **Pedidos (checkout PostgreSQL)** para converter o carrinho em pedido. O checkout exige o cabeçalho `Idempotency-Key` (gerado pela coleção), valida produto e forma de pagamento ativos e captura o preço diretamente do banco; nenhum preço é aceito do cliente.

O pedido e seus itens são persistidos no PostgreSQL em transação. Após a gravação bem-sucedida, o carrinho Redis é removido. Pedidos aguardando pagamento podem ser cancelados, mas permanecem armazenados com status `cancelado`.

## Contas de teste

O seeder `UsuarioTesteSeeder` cria as contas abaixo; todas usam a senha `Senha@12345`.

| Perfil | Login | E-mail |
|---|---|---|
| Cliente | `cliente-teste` | `cliente-teste@example.test` |
| Administrador | `admin-teste` | `admin-teste@example.test` |
| Super Administrador | `superadmin-teste` | `superadmin-teste@example.test` |
| Logística | `logistica-teste` | `logistica-teste@example.test` |

A coleção usa `superadmin-teste` por padrão nas requisições administrativas. Essas contas são exclusivamente para desenvolvimento local; não use as credenciais em ambientes compartilhados ou de produção.

O carrinho expira após `CARRINHO_TTL` segundos (padrão de 604800, ou 7 dias). Uma operação de escrita renova esse prazo. Cada item retorna `codigo`, `quantidade`, a data da última alteração e `modificado`, que informa se houve mudança na operação atual.

Após `migrate --seed`, a forma de pagamento com ID 1 é PIX e os produtos de demonstração usam IDs 1 e 2 em um banco vazio. Confira os IDs retornados pelo banco se já houver cadastros anteriores.

As requisições de logout e exclusão invalidam tokens. Para exercitar as duas, faça login novamente antes de enviar a requisição de exclusão.

## Produtos e estoque

A pasta **Produtos e Estoque** contém leitura pública do catálogo e operações administrativas. Faça login com uma conta Administrador para obter `adminToken`. Os seeders criam bandeira, botton e camiseta da LCB, cada um com 100 unidades de estoque inicial. As movimentações exigem direção, quantidade positiva e referência de nota. Saídas não podem exceder o saldo. A exclusão de uma movimentação é um estorno auditável; não apaga o registro original. A exclusão de produto o desativa para preservar o histórico.

O campo `codigo` Ã© o SKU textual, enquanto o `id` numÃ©rico continua sendo usado pelo carrinho e nas referÃªncias internas.

### Histórico de preços

O produto mantém o preço em registros históricos: a criação recebe o preço inicial e cada mudança usa POST /admin/produtos/{produto}/precos. GET nessa rota lista o histórico com o preço vigente primeiro. Atualizar os dados do produto não altera preços. O checkout captura o registro vigente e também grava o valor no item do pedido; preço histórico atual ou já usado em pedido não pode ser excluído. A collection inclui as chamadas para registrar, consultar e excluir um preço antigo sem referências.

