# Pendências do projeto

Atualizado em 20/10/2026. Entregas e validações já realizadas estão em [CONCLUIDAS.md](CONCLUIDAS.md), incluindo itens recuperados dos diffs e do histórico Git.

## Grade de tamanhos

- [ ] Configurar as grades e conferir o estoque físico de cada tamanho dos produtos de vestuário. Cadastro da grade, seleção na venda, reservas, movimentações e histórico por tamanho implementados; veja [Grades de tamanhos](docs/GRADES.md). Produtos antigos com movimentações sem tamanho exigem um novo cadastro com grade, sem apagar o histórico.

## Pagamentos — concluir antes de produção

- [ ] Confirmar novas entregas automáticas públicas e retentativas do Mercado Pago após a correção de caixa do ID. Homologação funcional aceita pelo responsável em 20/10/2026; seis capturas do simulador autenticadas e replay local com HTTP 200, deduplicação e conciliação em fila comprovados. Aplicação automática de teste `1044824833239556`, principal `880483999981556`; WCS ainda não informado. Ver `docs/MERCADO-PAGO-SUPORTE-HMAC.md`. Manter HMAC obrigatório e não repetir cobranças sem autorização.
- [ ] Comprovar habilitação e aprovação financeira do débito online e das demais bandeiras necessárias. Confirmar prazo de recebimento do débito e vigência das condições comerciais da conta.
- [ ] Validar compensação do boleto e completar o fluxo da loja em navegador: Pix e cartões, pendência, recusa, confirmação, retorno ao pedido e recarga sem duplicidade. Seguir a matriz do [relatório de homologação](docs/MERCADO-PAGO-HOMOLOGACAO.md); aceite do boleto digital e do formulário de débito já arquivados.
- [ ] Avaliar e atender os requisitos PCI DSS/SAQ A aplicáveis, incluindo o ambiente de produção. O formulário 16400 já foi enviado declarando não conformidade; falta a adequação. Ver [PCI-DSS.md](docs/PCI-DSS.md).
- [ ] Implementar cancelamentos e estornos remotos, conciliando pagamento, pedido e estoque.
- [ ] Permitir nova tentativa após rejeição definitiva, preservando idempotência e impedindo duplicidade quando o resultado anterior for incerto.
- [ ] Implementar CRUD de `ClienteCartao` integrado ao gateway: vinculação e remoção no provedor, referências cifradas, metadados mascarados e webhooks. Nunca receber nem persistir PAN/CVV.
- [ ] Concluir a revisão para produção: confirmar aplicabilidade do checklist oficial à API Orders, resolver lacunas aplicáveis, validar HTTPS/TLS na hospedagem final e atualizar relatos antigos de configuração. Ver [revisão atual](docs/MERCADO-PAGO-REVISAO.md).

## Fiscal

- [ ] Configurar conta, chave, empresa e certificado A1 na Fiscal Contora e validar emissão real em homologação pela coleção Postman. Confirmar cotas e condições do plano; não contratar saldo nem habilitar cobranças automaticamente.
- [ ] Definir com a Contabilidade os dados fiscais da loja e dos produtos: NCM, CFOP, CST/CSOSN, ICMS e regras tributárias.
- [ ] Ampliar emissão para frete, descontos, ST, DIFAL e demais cenários necessários; persistir os totais fiscais finais do XML autorizado e validar a parametrização.
- [ ] Completar correção/reenvio de notas rejeitadas, cancelamento, CC-e e reconciliação por webhooks autenticados.
- [ ] Validar emissão em produção e movimentações após autorização fiscal, sem duplicar a baixa de estoque do checkout.

Guia: [Fiscal Contora](docs/FISCAL-CONTORA.md).

## Entrega e logística

- [ ] Concluir a escolha e integração do serviço de postagem, etiqueta, rastreamento e devoluções. Cotação PAC/SEDEX pelo Hub, seleção no checkout e frete no pedido/pagamento já implementados; conferir medidas físicas e validar a operação. Ver [Frete](docs/FRETE.md). Avaliar elegibilidade do Mercado Envios para loja própria, cobertura por CEP, modalidades e custos; manter alternativa para pedidos externos não elegíveis.
- [ ] Definir webhooks de entrega com autenticação, assinatura, idempotência e tratamento de eventos repetidos ou fora de ordem.
- [ ] Implementar endpoints e processos operacionais com escopo OAuth2 `logistics:manage`, consulta de pedidos e acesso autorizado à DANFE e ao pacote.

## Dashboard e relatórios

- [ ] Completar Contabilidade, operações de Estoque e Manutenção.
- [ ] Implementar painel de Logística integrado aos endpoints operacionais de entrega.
- [x] Implementar cadastro/manutenção de categorias pela API com `nome_codigo` e `nome`, exclusão lógica e preservação dos vínculos de produtos. Tabela e chave estrangeira existentes reutilizadas; operações disponíveis no Postman.
- [ ] Implementar relatórios com inclusão explícita de registros excluídos logicamente, autorização e proteção de dados sensíveis.

Guia: [Dashboard](docs/DASHBOARD.md).

## Pendências antigas recuperadas do histórico

Removidas em `8e42039`, sem conclusão comprovada nesta revisão documental:

- [ ] Definir o provisionamento seguro do primeiro Administrador em produção; o seeder de contas privilegiadas atende desenvolvimento local.
- [ ] Decidir se `CarrinhoItem::alertarModificacao()` precisa emitir evento/notificação além do indicador `modificado`; o método atual fornece o indicador.
- [ ] Executar a validação final de fluxo e migrations no ambiente de publicação. Os testes direcionados já registrados não substituem essa etapa.

## Marketplace — somente se houver vendas no Mercado Livre

- [ ] Avaliar o emissor gratuito para vendas elegíveis do marketplace: conta PJ, inscrição estadual, A1, anúncios e naturezas atendidas. IDs locais e Orders Mercado Pago não substituem IDs de vendas Mercado Livre; não criar vendas fictícias para emitir notas externas.
- [ ] Mapear IDs externos de pedidos, pagamentos, notas e envios; sincronizar estoque sem duplicar movimentações e reconciliar notificações repetidas ou fora de ordem.

## Referências

- [Entregas concluídas e fontes históricas](CONCLUIDAS.md)
- [Mercado Pago: integração e operação](docs/MERCADO-PAGO.md)
- [Homologação: cenários, condições comerciais e evidências](docs/MERCADO-PAGO-HOMOLOGACAO.md)
- [Postman: requisições e fluxos](postman/README.md)
