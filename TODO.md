# Etapas deixadas para depois

- [ ] Definir as permissões exatas de cada `NivelAcesso` e aplicá-las às rotas administrativas.
- [ ] Definir o provisionamento seguro de um Administrador inicial em produção; o seeder atual de contas privilegiadas é apenas para desenvolvimento local.
- [ ] Implementar endpoints e regras de negócio de CRUD para Cliente, Administrador, Logística e Endereço, incluindo troca de endereço e autorização administrativa.
- [ ] Integrar o pedido a um provedor de pagamento; o checkout baixa o estoque contado em transacao, mas ainda nao considera reservas dos carrinhos.
- [x] Implementar catálogo de Produtos, imagens e controle de estoque por entradas, saídas e estornos auditáveis.
- [ ] Definir se `alertarModificacao()` deve publicar um evento/notificação além do indicador booleano `modificado` retornado na resposta.
- [ ] Avaliar atomicidade para atualizações concorrentes do mesmo carrinho Redis.
- [ ] Remover a chave Redis do carrinho quando o Cliente for excluído antes do TTL expirar.
- [ ] Definir o contrato de integração dos webhooks de pagamento e entrega: autenticação, assinatura, idempotência, tentativas e registro de eventos.
- [ ] Confirmar os países atendidos e adicionar validação baseada em códigos ISO; cidade permanece texto livre, pois não é um conjunto enumerável pequeno.
- [ ] Definir o padrão final das respostas da API e documentar os endpoints e payloads (por exemplo, OpenAPI).
- [ ] Avaliar verificação de e-mail, recuperação de senha, expiração/revogação de tokens e política de senha.
- [ ] Executar testes de fluxo e validar migrations no ambiente Docker antes da publicação.
- [ ] Implementar a API CRUD de `ClienteCartao` integrada por adaptador genérico de gateway: tokenizar e vincular o cartão no provedor, guardar somente referências cifradas e metadados mascarados, remover a referência no provedor e validar webhooks; nunca receber nem persistir PAN/CVV.
- [ ] Integrar `NotaFiscal` a um provedor fiscal: emissão, consulta, cancelamento, idempotência e reconciliação de status por webhook ou polling; persistir XML/DANFE em armazenamento apropriado e guardar somente seus caminhos no banco.
- [ ] Definir os dados fiscais obrigatórios da loja e dos produtos (NCM, CFOP, CST/CSOSN, ICMS e regras tributárias) antes de habilitar emissão em produção.
- [ ] Calcular disponibilidade como estoque contado (entradas menos saidas) menos quantidades provisionadas nos carrinhos Redis ativos; tratar concorrencia, TTL e liberacao/consumo da reserva no checkout.
