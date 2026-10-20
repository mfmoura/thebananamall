# Entregas concluídas

Consolidado em 19/10/2026 a partir do TODO, dos diffs locais, da documentação e do histórico Git. Implementação concluída não significa homologação externa ou liberação para produção. As etapas restantes estão em [TODO.md](TODO.md).

## Pagamentos — aceite em 20/10/2026

- [x] Homologação funcional aceita pelo responsável após a correção de assinatura local sem SDK. Mantidos HMAC obrigatório, registro durável, processamento em fila, recuperação e conciliação idempotente sem navegador.
- Evidências: crédito Visa/Mastercard/Amex e Pix aprovados no provedor; boleto digital aceito pelo responsável; seis capturas do simulador autenticadas; replay local retornou 200 duas vezes, um evento persistido e conciliação concluída. O simulador assinava a Order com ID original em maiúsculas; o receptor aceita esse formato e o ID em minúsculas, ambos com HMAC válido.
- Este aceite não declara liberação de produção nem comprova novas entregas automáticas públicas, aprovação de débito, compensação do boleto ou conformidade PCI. Pendências continuam em TODO.md; detalhes em [homologação](docs/MERCADO-PAGO-HOMOLOGACAO.md) e [diagnóstico HMAC](docs/MERCADO-PAGO-SUPORTE-HMAC.md).

## Grade de tamanhos — 20/10/2026

- [x] Grade configurável por produto, preço comum e estoque separado por tamanho, com configuração no Dashboard e escolha na loja.
- [x] Carrinho com tamanhos distintos do mesmo produto, reservas por tamanho, revalidação no checkout e estornos no tamanho original. Pedidos e itens fiscais preservam o tamanho.
- [x] Migration aditiva, índices por produto e tamanho, proteção do histórico e exemplos na pasta **Grade de tamanhos** do Postman. Operação descrita em [Grades de tamanhos](docs/GRADES.md).
- [x] Grade de demonstração P/M/G/GG aplicada aos sete vestuários existentes, sem histórico comercial: camiseta inicial com 25 unidades por tamanho e demais camisetas, regata, moletom e cropped com 8/8/7/7. Estorno da entrada original e novas entradas preservam os saldos totais e os registros anteriores. `ProdutoGradeTesteSeeder` integra a carga local; dois testes passaram (14 assertions), incluindo reexecução sem duplicidade e bloqueio com reserva ativa.
- Validação local: 57 testes PHPUnit passaram (367 assertions), cobrindo grade, catálogo, estoque, Dashboard, pedidos, frete e fiscal; 48 testes existentes de frontend passaram; build React concluído e migration aplicada no PostgreSQL local. Os testes de grade usam Redis simulado e banco SQLite isolado; não substituem conferência do estoque físico ou validação da operação em navegador.

## Campos de formulário — 20/10/2026

- [x] Controles de entrada e seleção da loja e Dashboard com bordas na cor da identidade visual, cantos arredondados, espaçamento e foco consistente; campos seguros do cartão seguem o mesmo contorno.
- [x] Espaço preservado para campos condicionais de tamanho, operação, referência de nota, dados da logística e banco emissor. Campos comuns ocultos ficam desabilitados, sem validação obrigatória nem envio; os controles internos do SDK preservam seu funcionamento. Tamanho e quantidade usam colunas estáveis na compra.
- Validação: 48 testes existentes de frontend passaram e build de produção concluído. A tentativa de captura visual com Edge headless não produziu imagem neste ambiente.

## Tabelas assíncronas — 20/10/2026

- [x] Overlay nos envios de formulários, alterações do carrinho, fechamento do pedido e solicitação/tokenização de pagamento. Consultas de páginas, dados e sessão usam skeleton sem overlay. O envio de pagamento libera o bloqueio após a resposta da API, sem aguardar a confirmação financeira assíncrona. Build concluído e 48 testes existentes de frontend passaram após o ajuste.

- [x] Skeleton compartilhado no carregamento de produtos, usuários, pedidos/expedição, itens de pedido, produtos de logística, estoque por tamanho e histórico de movimentações. Consultas das tabelas não usam overlay; filtros e navegação permanecem disponíveis. Indicador acessível e animação respeitam redução de movimento. Validação: build concluído e 48 testes existentes de frontend passaram.

## Recuperadas do histórico Git

Os itens abaixo haviam sido removidos ou resumidos em revisões do TODO. As referências identificam a origem, sem atribuir uma nova execução de testes nesta consolidação.

### API, conta e carrinho

- [x] Catálogo de produtos, imagens e controle de estoque por entradas, saídas e estornos auditáveis. Marcado como concluído no TODO de `f75e044`, removido em `8e42039`.
- [x] Permissões administrativas por páginas e proteção das rotas; restrição à concessão de permissões que o operador não possui. Fontes: `routes/api.php`, requests administrativos e [Dashboard](docs/DASHBOARD.md).
- [x] Endpoints de perfis e endereços com autorização, validação de país BR e cidade livre validada contra ViaCEP. Fontes: `routes/api.php`, requests de endereço e `ViaCepService`.
- [x] Confirmação/troca de e-mail, recuperação de senha e política de senha, com notificações assíncronas. Fontes: [política de senhas](docs/POLITICA-DE-SENHAS.md), notificações e instruções do projeto.
- [x] Concorrência do carrinho protegida por locks Redis e disponibilidade calculada considerando reservas; checkout serializado com operações de estoque. Fontes: `CarrinhoService`, `PedidoService`; pendências antigas substituídas em `8e42039`.
- [x] Exclusão de usuário centralizada, revogação de credenciais e liberação de carrinho/reservas, preservando histórico por exclusão lógica. Fonte: `UsuarioExclusaoService`.
- [x] Contrato de resposta existente e documentação de endpoints/payloads em Postman e OpenAPI. Fontes: `postman/README.md` e `docs/openapi.yaml`; a presença dos documentos não representa certificação de cobertura integral.

### Loja e administração

- [x] Frontend React com catálogo, detalhe de produto, cadastro, login OAuth2/PKCE, carrinho, conta, endereços e pedidos. Fonte: `8e42039` e `resources/js`.
- [x] Rodapé institucional, skeleton loaders e melhorias de desempenho/renderização. Fontes: `a7c9b24`, `b5311a4` e [diagnóstico de desempenho](docs/DESEMPENHO.md).
- [x] BFF com cookies HttpOnly/CSRF, tokens cifrados no servidor e proxy com rotas explícitas. Fonte: [BFF](docs/BFF.md).
- [x] Dashboard com login administrativo separado, CRUDs de produtos e usuários e upload de fotos. Fonte: `82cdd2b` e [Dashboard](docs/DASHBOARD.md).
- [x] Consulta administrativa de pedidos, cancelamento local quando permitido, acesso à DANFE e impressão de etiqueta de destinatário. Fonte: `a7dd16c`. Etiqueta de destinatário não equivale à etiqueta de postagem; cancelamento remoto do pagamento continua pendente.
- [x] Entrada HTTP centralizada no Nginx e isolamento das redes PHP, Vite, banco e Redis. Fonte: `5d91255` e [avaliação PCI](docs/PCI-DSS.md); não comprova conformidade PCI.
- [x] Melhorias visuais dos campos seguros de cartão e sombras dos cards do checkout. Fonte: `d011d5f`.

### Base de pagamentos e fiscal

- [x] Integração backend Mercado Pago Orders: criação e consulta, referência externa, idempotência persistida, assinatura de webhook e validação de valor/moeda/referência antes da confirmação. Item concluído em `0e41b66`, ampliado em `82cdd2b` e retirado do TODO em `f5fbd1e`.
- [x] Checkout React com tokenização MercadoPago.js, Pix e acompanhamento integrado. Fonte: `82cdd2b`.
- [x] Pagamentos em fila Redis dedicada: HTTP 202, snapshot cifrado, retentativas idempotentes e conciliação independente do navegador. Item concluído em `82cdd2b`, retirado do TODO em `f5fbd1e`.
- [x] Suporte a boleto na integração e no checkout. Fonte: `a7dd16c`.
- [x] Integração Fiscal Contora, processamento fiscal assíncrono e geração local de DANFE por NFePHP. Fontes: `8e42039` e [guia fiscal](docs/FISCAL-CONTORA.md). Emissão real e ciclo fiscal completo continuam pendentes.

## Entregas dos diffs locais e registros de homologação

Itens presentes na árvore de trabalho em 19/10/2026, inclusive arquivos novos ainda sem commit. Não foram criados commits nesta consolidação.

### Pagamentos — implementação e diagnóstico

- [x] Tratamento de HTTP 402 de Orders: preservação do ID, consulta autenticada e retomada sem repetir cobrança. Fontes: `app/Webservices/MercadoPagoClient.php`, `MercadoPagoModalidadesTest` e relatório de homologação.
- [x] SDK oficial PHP `mercadopago/dx-php 3.16.0` instalado e integrado à validação de webhook; falha de assinatura retorna 401. SDK também reproduziu SignatureMismatch das capturas automáticas; o problema externo permanece aberto.
- [x] Diagnóstico HMAC com vetor conhecido, comparação da configuração e controles sintéticos. Não houve relaxamento da assinatura. Evidências e limites no [relatório de homologação](docs/MERCADO-PAGO-HOMOLOGACAO.md).
- [x] Clientes externos de Mercado Pago, Fiscal Contora e ViaCEP separados em `app/Webservices`, com injeção nos services. Regras de negócio permanecem nos services; testes de ViaCEP adicionados, sem nova execução nesta consolidação documental.
- [x] E-mail de pagamento confirmado enfileirado após commit, uma vez por transição do pedido, sem envio para contas/perfis excluídos. Fontes: `PagamentoConfirmadoNotification`, `PedidoService` e testes de pagamentos.
- [x] Atualizações da coleção Postman e instruções operacionais para pagamentos, webhook e confirmação assíncrona. Fontes: diffs de `postman/` e `docs/MERCADO-PAGO.md`.

### Pagamentos — evidências remotas e aceite

- [x] Crédito Visa, Mastercard e Amex aprovados no provedor de teste via Orders. Evidência histórica; não representa todas as bandeiras ou todo o fluxo em navegador.
- [x] Pix emitido com instruções e aprovado em teste; boleto emitido com linha digitável e documento HTTPS. Boleto continua sem compensação comprovada.
- [x] Fluxo API/job contra provedor de teste validado com SQLite em memória e filas interceptadas: registro de 1 teste, 170 assertions. Horizon real também confirmou pedidos fictícios 6 e 7.
- [x] Oito resultados de titular consultados no provedor: APRO aprovado, CONT pendente e seis recusas. As recusas não geram nova cobrança na retomada.
- [x] Condições comerciais informadas pelo responsável registradas: Pix 0,99%; crédito à vista 4,98%, na hora; boleto R$ 3,49, recebimento em 3 dias; débito 4,98%. Disponibilidade e prazo do débito, além da vigência contratual, permanecem pendentes.
- [x] Suporte acionado para divergência de assinatura da aplicação vendedora de teste. Número WCS e solução ainda aguardados; registro do acionamento não equivale à resolução.
- [x] Consulta somente leitura das oito Orders de teste mais recentes confirmou estados locais/remotos: Pix e Mastercard aprovados; boleto do pedido 15 aguardando pagamento; Pix do pedido 17 aguardando transferência. Nenhum débito nessa amostra e nenhuma nova cobrança criada.
- [x] Checklist oficial consultado e todos os 19 requisitos e 15 boas práticas classificados, com ressalvas de aplicabilidade a Orders. Fonte: [revisão](docs/MERCADO-PAGO-REVISAO.md).
- [x] Formulário oficial **16400** enviado e salvo para aplicação **880483999981556**, produto 24, com varejo, uma marca LCB, Brasil, uma conta e Pix/boleto/crédito/débito configurados. Por declaração explícita do responsável, `ou_api_integration_model=no` (ainda não cumpre PCI DSS). O salvamento não certifica conformidade nem libera produção.

### Tela de pagamento

- [x] Pix, boleto, crédito e débito no seletor de novas compras. Débito reexibido a pedido do responsável; aprovação financeira ainda pendente.
- [x] Estados de preparação, pendência, recusa, confirmação, expiração, cancelamento, estorno, contestação e verificação, dependentes do resultado da API; instruções antigas ocultas e ausência de nova cobrança quando o resultado é incerto.
- [x] Boleto digital homologado pelo responsável em 19/10/2026, incluindo aceite da apresentação da linha digitável e cópia integrada. Não foram fornecidas evidências individuais de impressão/clipboard; vale o aceite funcional registrado.
- [x] Chave pública de teste corrigida via MCP; consulta pública de documentos passou de HTTP 400 para 200, com CPF/CNPJ. Credenciais preservadas fora do versionamento.
- [x] Apresentação e preenchimento do formulário de débito aceitos pelo responsável após a correção da chave. Aceite visual não comprova aprovação do pagamento.
- [x] Recuperação dos campos de cartão: limite de montagem de 30 segundos, descarte de callbacks tardios, mensagem para ausência de documentos e remontagem sem criar cobrança.
- [x] Cronômetro e barra de pagamento usando datas absolutas do provedor, preservados na conciliação e após recarga; sem prazo fictício quando não há expiração.
- [x] Acompanhamento automático do pagamento e do pedido, sem depender de confirmação pelo estado do navegador.

### Validação já executada

- [x] **63 testes PHP aprovados, 524 assertions** na revisão de 19/10/2026: MercadoPagoTest, MercadoPagoModalidadesTest, PixBoletoTest e MercadoPagoConfiguracaoTest.
- [x] **45 testes de frontend aprovados** na mesma revisão: carregamento de cartão, estados, HTML renderizado e tempo de pagamento.
- [x] Builds e validações direcionadas anteriores registrados no relatório de homologação. Contagens de execuções anteriores não devem ser somadas como testes distintos.

Os testes automatizados com respostas/SDK/DOM controlados não substituem navegador real, aprovação financeira do débito, compensação de boleto, webhooks automáticos ou avaliação PCI. Esta limpeza altera somente documentação; não executa novamente testes nem realiza transações.

## Fontes e rastreabilidade

- Histórico completo de `TODO.md`: `git log -p -- TODO.md`.
- Recuperação principal: `f75e044`, `8e42039`, `0e41b66`, `82cdd2b`, `f5fbd1e`, `a7dd16c`, `60bd442`.
- Alterações ainda locais: `git diff` e `git status --short`, incluindo arquivos não rastreados.
- [Homologação Mercado Pago](docs/MERCADO-PAGO-HOMOLOGACAO.md), [revisão](docs/MERCADO-PAGO-REVISAO.md) e [PCI DSS](docs/PCI-DSS.md).
- [Operação Mercado Pago](docs/MERCADO-PAGO.md), [Fiscal Contora](docs/FISCAL-CONTORA.md), [Dashboard](docs/DASHBOARD.md) e [Postman](postman/README.md).
