# Etapas deixadas para depois

- [ ] Completar a homologação do Mercado Pago: testar Pix e demais bandeiras/modalidades, confirmar disponibilidade e tarifas na conta, validar entrega automática dos webhooks e concluir a revisão antes de produção. Implementar cancelamentos/estornos remotos, sua conciliação e novas tentativas após rejeição. Checkout, tokenização, pagamento assíncrono e aprovação de Mastercard de teste já validados; guia em `docs/MERCADO-PAGO.md`.
- [ ] Completar o contrato operacional dos webhooks de pagamento (entrega automática, retentativas e registro de eventos) e definir o contrato dos webhooks de entrega, incluindo autenticação, assinatura e idempotência.
- [ ] Completar as telas administrativas de Contabilidade, operações de Estoque e Manutenção além dos CRUDs de produtos e usuários já implementados; implementar o painel de Logística. O dashboard já possui login administrativo separado e upload de fotos.
- [ ] Implementar a API CRUD de `ClienteCartao` integrada por adaptador genérico de gateway: tokenizar e vincular o cartáo no provedor, guardar somente referéncias cifradas e metadados mascarados, remover a referéncia no provedor e validar webhooks; nunca receber nem persistir PAN/CVV.
- [ ] Completar o ciclo Fiscal Contora além da homologação já implementada: correção/reenvio de notas rejeitadas, cancelamento, CC-e e reconciliação por webhooks autenticados. Preparar emissão em produção e movimentação de estoque somente após autorização real, sem duplicar a baixa feita pelo checkout.
- [ ] Ampliar o payload fiscal de emissão conforme os cenários da loja: frete, descontos, ST, DIFAL e demais grupos necessários. Persistir os totais fiscais finais do XML autorizado e validar a parametrização com a Contabilidade antes de produção.
- [ ] Definir os dados fiscais obrigatórios da loja e dos produtos (NCM, CFOP, CST/CSOSN, ICMS e regras tributárias) antes de habilitar emissão em producção.
- [ ] Implementar endpoints e processos de operção para Logística, com escopo OAuth2 `logistics:manage`, leitura de pedidos e acesso autorizado à DANFE e ao pacote.

## Infraestrutura Mercado Livre e Mercado Pago

- [ ] Configurar a conta Fiscal Contora e validar uma emissão real em homologação: gerar chave, cadastrar empresa e certificado A1, preencher credenciais locais e executar a pasta Postman. O provedor anuncia 100 eventos de homologação e 500 de produção por mês no plano gratuito; confirmar as condições no cadastro, pois eventos não equivalem necessariamente a notas. Não contratar saldo adicional nem habilitar cobranças automaticamente. Integração e geração local do PDF com NFePHP já implementadas; guia em `docs/FISCAL-CONTORA.md`.

- [ ] Usar o emissor gratuito do Mercado Livre para vendas elegíveis do marketplace, conforme preferência definida. A API documentada exige IDs de vendas do Mercado Livre (`POST /users/{user_id}/invoices/orders`, corpo `orders`); IDs dos pedidos locais ou das Orders do Mercado Pago não substituem esses IDs. Para emitir notas das vendas da loja própria, falta um fluxo oficialmente suportado para pedidos externos ou a escolha de outro emissor. Não criar vendas fictícias no marketplace para contornar a restrição. Verificar conta PJ, inscrição estadual, certificado A1, cadastro fiscal dos anúncios e cobertura das naturezas necessárias antes de implementar o adaptador.
- [ ] Avaliar Mercado Envios para cotação, etiquetas, rastreamento e devoluções: confirmar disponibilidade contratual para pedidos da loja própria, cobertura por CEP, modalidades e custos. A API documentada trabalha com envios associados às operações do marketplace; manter opção de outro provedor caso pedidos externos não sejam elegíveis.
- [ ] Se houver vendas também no marketplace, mapear IDs externos de pedidos, pagamentos, notas e envios; sincronizar estoque sem duplicar as movimentações já registradas no checkout e reconciliar notificações repetidas ou fora de ordem.
- [ ] Confirmar quais modalidades de débito são efetivamente suportadas pela conta Mercado Pago e homologar o fluxo com cartão de débito.

Referências oficiais consultadas em 16/10/2026:

- Nuvem Fiscal descartada: o comunicado oficial anunciou desativação em 31/07/2026. [Comunicado](https://www.nuvemfiscal.com.br/form-sub/).
- [Fiscal Contora: plano gratuito, cotas e requisitos](https://fiscal.contora.com.br/blog/api-fiscal-gratuita)
- [Fiscal Contora: documentação da API](https://fiscal.contora.com.br/documentacao)
- [NFePHP: geração local de DANFE](https://github.com/nfephp-org/sped-da)

- [Checkout Mercado Pago para lojas próprias](https://empresas.mercadopago.com.br/checkout-mercado-pago-e-checkout-transparente-entenda-as-diferencas)
- [Checkout Transparente: API e tokenização](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api/overview)
- [Checkout Transparente via Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-model)
- [Emissor gratuito de notas fiscais do Mercado Livre](https://vendedores.mercadolivre.com.br/nota/como-emitir-as-notas-fiscais-das-minhas-vendas?guideKeyId=GE74&moduleKeyId=MO341)
- [API de faturamento Mercado Livre](https://developers.mercadolivre.com.br/pt_br/api-fiscal-faturamento-de-venda)
- [Gerenciamento de envios](https://developers.mercadolivre.com.br/pt_br/registre-o-seu-aplicativo/gerenciamento-de-envios)

- [ ] Implementar relatórios que consultem também registros excluídos logicamente, preservados permanentemente, com autorização e proteção dos dados sensíveis.

- [ ] Implementar cadastro e manutenção de categorias no dashboard admin, com nome_codigo para identificação e nome para exibição nos fronts, respeitando exclusão lógica e vínculos dos produtos.
