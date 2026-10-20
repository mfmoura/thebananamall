# Grades de tamanhos

O produto pode ter uma grade própria, como `["P", "M", "G", "GG"]` ou `["38", "40", "42"]`. Nome, fotos, preço, tributação e embalagem pertencem ao produto. Saldo, reserva, entrada, saída e estorno distinguem produto e tamanho. Produtos sem grade mantêm o comportamento anterior.

## Configuração e operação

No Dashboard, em Produtos, informe os tamanhos separados por vírgula. Na Logística, consulte os saldos por tamanho e selecione o tamanho em cada movimentação. Entradas com referência de nota também exigem tamanho. O histórico conserva esse campo.

Na loja, a página do produto exige a seleção de um tamanho disponível. Dois tamanhos do mesmo produto são itens separados no carrinho. O tamanho acompanha o pedido, a conferência no Dashboard e os itens fiscais. O preço é comum a todos os tamanhos e quantidades de produtos com grade devem ser inteiras.

O estoque existente não é distribuído automaticamente. Para preservar o histórico, um produto com movimentações sem tamanho exige um novo cadastro com grade. Faça as correções por movimentações e estornos autorizados, sem apagar registros. A edição permite acrescentar e reordenar tamanhos, mas não remover nem renomear os existentes, inclusive quando houver reservas em carrinhos.

## API

- `POST /api/admin/produtos` e `PATCH /api/admin/produtos/{id}` aceitam `grade`, um array de até 50 nomes de até 30 caracteres, sem duplicatas. `[]` representa um produto sem grade.
- `GET /api/produtos` e `GET /api/produtos/{id}` incluem `grade` e `estoque_por_tamanho`: tamanho, estoque contado, reservado e disponível (`estoque`). Os totais existentes continuam representando a soma do produto.
- `GET /api/admin/logistica/produtos/{id}` inclui os mesmos saldos por tamanho.
- `GET /api/produtos/{id}/estoque` também retorna `estoque_por_tamanho`, mantendo os totais anteriores.
- Movimentações em `POST /api/admin/estoque/movimentacoes` aceitam `tamanho`. O campo é obrigatório quando o produto tem grade e deve corresponder exatamente ao nome cadastrado.
- `POST /api/carrinho/itens` aceita `codigo`, `quantidade` e `tamanho`. `codigo` continua sendo o ID do produto.
- `PATCH /api/carrinho/itens/{codigo}` aceita `quantidade` e `tamanho`. Para excluir, envie `DELETE /api/carrinho/itens/{codigo}?tamanho=M` (codifique o valor na URL).
- `PUT /api/carrinho` aceita tamanhos diferentes do mesmo produto; repetir a combinação de produto e tamanho é inválido.
- Itens de pedido e nota fiscal persistem `tamanho`. Registro manual e emissão fiscal aceitam `itens.*.tamanho`; notas vinculadas à venda conferem quantidades por produto e tamanho.

Autenticação, escopos e permissões das rotas permanecem aplicáveis. O BFF encaminha os mesmos campos e a query string, mantendo os tokens somente na sessão do servidor.

As reservas mantêm os prazos existentes e o mesmo bloqueio global. Consultas e reenvios de quantidades idênticas não renovam a reserva. Checkout revalida o saldo de cada tamanho; cancelamentos elegíveis e estornos devolvem o saldo ao tamanho original.

## Instalação e validação

### Grade de demonstração nos vestuários existentes

Em ambiente local, execute `docker compose exec php php artisan db:seed --class=ProdutoGradeTesteSeeder`. O seeder também faz parte da carga local padrão. Aplica P, M, G e GG apenas aos sete códigos conhecidos de camisetas, regata, moletom e cropped de demonstração, desde que ainda não tenham grade, pedidos, notas fiscais ou reservas ativas. A conversão é uma exceção restrita aos dados de teste; a regra de alteração da API permanece preservada.

O saldo inteiro existente é distribuído igualmente, com o restante nos primeiros tamanhos: 30 unidades viram P=8, M=8, G=7, GG=7; 100 unidades viram 25 de cada. O seeder exige somente a entrada inicial sem tamanho (ou nenhum movimento). Registra um estorno vinculado à entrada original e novas entradas por tamanho na mesma transação e sob o bloqueio de estoque. Nenhum registro antigo é alterado ou excluído. Reexecuções não duplicam saldo nem sobrescrevem grades já configuradas.

Execute `docker compose exec php php artisan migrate` para adicionar as colunas de grade e tamanho. Nenhum saldo antigo é alterado. Não edite diretamente o JSON da grade no banco.

A pasta **Grade de tamanhos** do Postman usa `produtoGradeId` e `tamanhoGrade`. Antes de substituir o carrinho com P e M, registre entradas nos dois tamanhos. Para realizar checkout, preencha também a embalagem, cote frete e siga o fluxo existente de pedidos.
