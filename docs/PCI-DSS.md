# PCI DSS — avaliação inicial da The Banana Mall

Avaliação inicial em 18/10/2026. Este documento registra evidências técnicas e lacunas; não é auditoria, SAQ preenchido nem declaração de conformidade.

## Escopo e responsabilidade

A loja usa Checkout Transparente via Orders, com MercadoPago.js v2 e campos seguros em iframe. O Mercado Pago indica SAQ A para esse modelo. A elegibilidade e os requisitos aplicáveis devem ser confirmados para a implementação e o ambiente de produção, incluindo fornecedores. O uso de campos seguros reduz o escopo; não comprova sozinho conformidade.

## Evidências locais

| Controle observado | Evidência | Limite da verificação |
| --- | --- | --- |
| Campos sensíveis hospedados pelo provedor | `resources/js/hooks/useCartaoMercadoPago.js`, `iframe: true` | Interação real e integridade da página em produção ainda não avaliadas |
| SDK obtido via HTTPS do domínio oficial | `resources/js/services/mercadoPago.js` | Não comprova proteção contra alterações ou injeção de outros scripts |
| API de pagamento proíbe PAN e CVV | `app/Http/Requests/Api/CriarPagamentoRequest.php` | Revisar também logs, telemetria e demais entradas em produção |
| Token temporário e snapshot ocultos e cifrados | `app/Models/PedidoPagamento.php` e `app/Services/MercadoPagoService.php` | Gestão de chaves, backups e acessos operacionais ainda não avaliados |
| Credenciais privadas permanecem no servidor | Configuração de Mercado Pago e BFF | Rotação, inventário de acessos e MFA da operação ainda não avaliados |
| Cookies seguros fora do ambiente local | `config/session.php` | HTTPS, TLS e cabeçalhos efetivos devem ser conferidos na hospedagem final |
| Autorização e resultados financeiros verificados no servidor | Testes de pagamentos, propriedade e conciliação | Homologação funcional não substitui avaliação PCI |

## Entrada HTTP e isolamento no Docker Compose

O Nginx é a única entrada HTTP da aplicação, na porta 80 do ambiente local. PHP-FPM e Node/Vite não publicam portas no host e usam redes distintas, cada uma compartilhada apenas com o Nginx. Assets e WebSocket de desenvolvimento passam por `/vite/`. PostgreSQL e Redis ficam na rede interna `dados`, compartilhada somente com PHP, Horizon e scheduler. Redis não publica portas no host; PostgreSQL publica `127.0.0.1:5432:5432`, permitindo administração somente pelo próprio computador. PHP e workers mantêm saída para pagamento, provedor fiscal e SMTP; Node mantém saída para instalação de dependências. O túnel opcional ngrok alcança somente o Nginx, com painel de diagnóstico limitado ao loopback do host.

Esse controle reduz exposição direta e limita comunicação entre containers. O host Docker e administradores do daemon continuam privilegiados; requisições às rotas publicadas ainda chegam ao Laravel pelo Nginx. A mudança não comprova conformidade PCI DSS nem retira automaticamente componentes do escopo. Produção ainda exige HTTPS/TLS e verificação das regras efetivas na hospedagem. Vite é ferramenta de desenvolvimento: em produção usar assets compilados e manter Node parado.

Verificação local em 18/10/2026 após recriar os containers: Compose e `nginx -t` válidos; página inicial, sessão BFF, catálogo e assets via Nginx responderam HTTP 200; handshake HMR respondeu HTTP 101. As portas 5173, 5432 e 9000 recusaram conexão pelo loopback do host. Node alcançou Nginx, mas não PHP, PostgreSQL ou Redis por seus IPs internos; Nginx também não alcançou PostgreSQL ou Redis por IP. Essas evidências são do Docker local, não da hospedagem de produção.

Atualização em 19/10/2026: acesso administrativo local ao PostgreSQL habilitado em `127.0.0.1:5432`, com rede `db_local` exclusiva do banco para viabilizar essa publicação no Docker. A rede `dados` permanece interna. A verificação de bloqueio da porta 5432 registrada acima descreve a configuração anterior de 18/10/2026.

## Trabalho necessário para a declaração

- Confirmar com Mercado Pago o processo de validação, a elegibilidade ao SAQ A e os documentos exigidos para a conta.
- Avaliar a proteção da página contra ataques por scripts, mantendo evidência dos mecanismos adotados ou da confirmação aplicável do provedor. Não foi encontrada configuração de CSP nos arquivos de middleware e Nginx examinados; CSP é um mecanismo possível e sua presença isolada também não comprova atendimento.
- Avaliar o ambiente real: TLS, hospedagem, contas e privilégios, autenticação administrativa, atualizações, gestão de vulnerabilidades, logs, backups e resposta a incidentes, conforme o questionário aplicável.
- Preencher o SAQ e a declaração correspondente com o responsável pela operação, registrar evidências e manter a validação exigida. Não atribuir um percentual com base apenas nos controles encontrados no código.

## Formulário de homologação

Em 19/10/2026, o responsável escolheu explicitamente “Declarar que ainda não cumpre o padrão”. O formulário MCP de Checkout API foi enviado com `ou_api_integration_model=no`, para a aplicação `880483999981556`, produto 24, Brasil. O Mercado Pago respondeu `Form saved successfully`, formulário **16400**, `needs_review=false`. Foram informados varejo, uma marca, um país, uma conta por país e Pix/boleto/crédito/débito configurados. O retorno confirma o salvamento do formulário, não comprova conformidade PCI, aprovação financeira de todas as modalidades ou liberação de produção. A avaliação e adequação PCI permanecem pendentes.

## Fontes

- [Mercado Pago — PCI DSS e SAQ por integração](https://www.mercadopago.com/developers/pt/docs/security/pci)
- [PCI Council — critérios de elegibilidade do SAQ A para proteção contra scripts](https://www.pcisecuritystandards.org/faqs/1588/)
- [PCI Council — todos os critérios de elegibilidade devem ser atendidos](https://www.pcisecuritystandards.org/faqs/if-a-merchant-s-e-commerce-implementation-meets-the-criteria-that-all-elements-of-payment-pages-originate-from-a-pci-dss-compliant-service-provider-is-the-merchant-eligible-to-complete-saq-a-or-saq-a-ep/)
