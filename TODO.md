# Pendências do projeto

## Mercado Pago — pagamentos

Checkout, tokenização e processamento assíncrono estão implementados para Pix, boleto, crédito e débito. Há aprovação de Mastercard de teste registrada; testes PHPUnit com respostas simuladas não substituem a homologação dos demais meios.

- [ ] Homologar Pix, boleto e demais bandeiras de crédito/débito na conta. Confirmar modalidades habilitadas e tarifas.
  - Consulta remota de teste: Visa, Amex e Mastercard aprovados diretamente em Orders; Pix em processamento sem QR Code; boleto falhou com `processing_error`; débito Elo recusado para o recebedor. Tarifas e fluxo completo da loja pendentes. Evidências em [docs/MERCADO-PAGO-HOMOLOGACAO.md](docs/MERCADO-PAGO-HOMOLOGACAO.md).
- [ ] Validar entrega automática dos webhooks de pagamento, retentativas e registro dos eventos, incluindo conciliação sem o navegador aberto.
- [ ] Implementar cancelamentos e estornos remotos, conciliando pagamento, pedido e estoque.
- [ ] Permitir novas tentativas após rejeição definitiva, sem duplicar cobranças quando o resultado anterior for incerto.
- [ ] Concluir a revisão da integração antes de produção e atualizar os relatos antigos de configuração/homologação na documentação.
- [ ] Implementar a API CRUD de `ClienteCartao` com adaptador de gateway: tokenizar e vincular o cartão no provedor, guardar somente referências cifradas e metadados mascarados, remover a referência no provedor e validar webhooks. Nunca receber nem persistir PAN/CVV.

## Entrega e logística

A etiqueta de destinatário do dashboard já pode ser impressa; a emissão de etiqueta de postagem por transportadora permanece pendente.

- [ ] Escolher e integrar um provedor para cotação de frete, etiquetas de postagem, rastreamento e devoluções. Avaliar Mercado Envios para pedidos da loja própria, confirmando elegibilidade contratual, cobertura por CEP, modalidades e custos; manter alternativa caso pedidos externos ao marketplace não sejam elegíveis.
- [ ] Definir os webhooks de entrega, incluindo autenticação, assinatura, idempotência e tratamento de notificações repetidas ou fora de ordem.
- [ ] Implementar endpoints e processos operacionais de Logística com escopo OAuth2 `logistics:manage`, leitura de pedidos e acesso autorizado à DANFE e ao pacote.

## Fiscal

A integração Fiscal Contora e a geração local de DANFE com NFePHP estão implementadas. A emissão real em homologação ainda precisa ser validada.

- [ ] Configurar a conta Fiscal Contora: gerar chave, cadastrar empresa e certificado A1, preencher credenciais e validar uma emissão real pela coleção Postman. Confirmar cotas e condições do plano gratuito; eventos não equivalem necessariamente a notas. Não contratar saldo adicional nem habilitar cobranças automaticamente.
- [ ] Definir os dados fiscais obrigatórios da loja e dos produtos: NCM, CFOP, CST/CSOSN, ICMS e regras tributárias.
- [ ] Ampliar o payload de emissão para frete, descontos, ST, DIFAL e demais cenários necessários. Persistir os totais fiscais finais do XML autorizado e validar a parametrização com a Contabilidade.
- [ ] Completar correção/reenvio de notas rejeitadas, cancelamento, CC-e e reconciliação por webhooks autenticados.
- [ ] Preparar emissão em produção e movimentação de estoque somente após autorização fiscal real, sem duplicar a baixa feita pelo checkout.
- [ ] Para vendas elegíveis do marketplace, avaliar o emissor gratuito do Mercado Livre. A API exige IDs de vendas do Mercado Livre; pedidos locais e Orders do Mercado Pago não os substituem. Verificar conta PJ, inscrição estadual, certificado A1, cadastro fiscal dos anúncios e naturezas atendidas. Não criar vendas fictícias para emitir notas de pedidos externos; usar um fluxo oficialmente suportado.

## Front da loja

O checkout já oferece Pix, boleto e cartões; o acompanhamento usa o estado confirmado pela API.

- [ ] Validar os estados visuais e as instruções das modalidades durante a homologação de pagamentos, incluindo linha digitável/impressão do boleto, pendência, recusa e confirmação.

## Dashboard

Já implementados: login administrativo separado, produtos, usuários, upload de fotos e consulta de pedidos com cancelamento permitido, DANFE e etiqueta de destinatário. Pedidos não permitem edição.

- [ ] Completar as telas de Contabilidade, operações de Estoque e Manutenção.
- [ ] Implementar o painel de Logística integrado aos endpoints operacionais de entrega.
- [ ] Implementar cadastro e manutenção de categorias com `nome_codigo` para identificação e `nome` para exibição, respeitando exclusão lógica e vínculos dos produtos.
- [ ] Implementar relatórios que incluam explicitamente registros excluídos logicamente, preservados permanentemente, com autorização e proteção dos dados sensíveis.

## Integração com marketplace — se houver vendas no Mercado Livre

- [ ] Mapear IDs externos de pedidos, pagamentos, notas e envios; sincronizar estoque sem duplicar movimentações do checkout e reconciliar notificações repetidas ou fora de ordem.

## Referências

### Guias do projeto

- [Mercado Pago: integração, testes e operação](docs/MERCADO-PAGO.md)
- [Fiscal Contora](docs/FISCAL-CONTORA.md)
- [Dashboard](docs/DASHBOARD.md)
- [Postman: fluxos e requisições](postman/README.md)

### Documentação externa

- [Mercado Pago: referência da API Orders](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-api/overview)
- [Mercado Pago: modelo de integração Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/integration-model)
- [Mercado Pago: boleto via Orders](https://www.mercadopago.com.br/developers/pt/docs/checkout-api-orders/payment-integration/websites/boleto)
- [Fiscal Contora: documentação da API](https://fiscal.contora.com.br/documentacao)
- [Fiscal Contora: plano gratuito, cotas e requisitos](https://fiscal.contora.com.br/blog/api-fiscal-gratuita)
- [NFePHP: geração local de DANFE](https://github.com/nfephp-org/sped-da)
- [Mercado Livre: API de faturamento](https://developers.mercadolivre.com.br/pt_br/api-fiscal-faturamento-de-venda)
- [Mercado Livre: gerenciamento de envios](https://developers.mercadolivre.com.br/pt_br/registre-o-seu-aplicativo/gerenciamento-de-envios)
