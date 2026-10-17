# Fiscal Contora — homologação

Cadastro: https://fiscal.contora.com.br/register

Esta integração implementa NF-e modelo 55 de teste: rascunho, transmissão assíncrona, consulta, armazenamento privado do XML e geração local do DANFE com NFePHP. Não habilita emissão em produção. Notas de homologação não movimentam estoque, mesmo quando autorizadas pela SEFAZ de testes.

## Configuração

O Docker Compose também inicia `horizon` e `scheduler`. A conexão de fila é Redis, com fila `fiscal` separada da `default`; o PHP inclui `pcntl`, exigida pelo Horizon. O agendador recupera publicações pendentes a cada minuto e não confirma pagamentos.

## Compra e emissão assíncrona

O checkout `POST /api/pedidos` recebe `forma_pagamento_id` e `dados_fiscais` com o mesmo formato de `destinatario` do exemplo de emissão. Preço, quantidade, tributos e numeração são resolvidos no backend; o Cliente não envia payload fiscal do provedor. A resposta mantém HTTP 201 por criar o pedido e inclui:

```json
{"processamento_fiscal":{"status":"processando","mensagem":"Seus dados estão sendo processados.","nota_fiscal_id":null}}
```

A aplicação registra o acompanhamento e os dados de faturamento cifrados na mesma transação do pedido. Não consulta o Fiscal Contora nem o ViaCEP nessa requisição. Quando o backend confirma o pagamento, o observer despacha o job após commit. A integração do Mercado Pago ainda está pendente; o gateway deverá chamar `PedidoService::registrarPagamentoConfirmado` somente após verificar a confirmação remota. Não existe endpoint público para o Cliente marcar seu pedido como pago.

O job consulta o CEP, prepara a nota de venda com o preço capturado no pedido e a configuração tributária dos itens e reutiliza uma chave de idempotência por pedido. A consulta de autorização e a geração do DANFE ocorrem em segundo plano. A numeração automática usa uma sequência bloqueada no PostgreSQL por CNPJ, ambiente e série e respeita os números já registrados. Configure `FISCAL_CONTORA_UF_EMITENTE` e `FISCAL_CONTORA_SERIE`; mantenha a emissão em homologação.

Cadastre `configuracao_fiscal` nos produtos pelos endpoints administrativos existentes, por exemplo:

```json
{"configuracao_fiscal":{"ncm":"63079090","cfop_interno":"5102","cfop_interestadual":"6102","unidade":"UN","tributos":{"icms":{"code":"102","origin":0}}}}
```

Os códigos são exemplos de homologação e precisam de validação da Contabilidade. O sistema escolhe CFOP interno ou interestadual conforme a UF do destinatário. A configuração dos itens é capturada no checkout; produtos antigos sem configuração aguardam preenchimento administrativo, sem inventar tributação. Pendências são verificadas novamente em cinco minutos.

`GET /api/pedidos/{id}` permite ao próprio Cliente consultar `processamento_fiscal`. Quando concluído, `GET /api/pedidos/{id}/danfe` permite baixar o PDF. Enquanto não estiver pronto, o download retorna 409 com mensagem de processamento. Erros técnicos e os dados fiscais cifrados não aparecem nessa consulta pública.

Os jobs contêm somente o ID do acompanhamento. Há exclusão mútua por acompanhamento, retentativas com backoff e retomada da mesma nota após falhas; autorização pendente libera o job por 60 segundos antes de consultar novamente. O registro no banco permite reenfileirar após indisponibilidade do Redis. Após esgotar tentativas, o job fica em falha no Horizon para análise e retry do job original; nunca gere outra chave para contornar uma falha de rede.

O Horizon usa timeout de 210 segundos, jobs fiscais de 180 segundos e `retry_after` de 240 segundos, contemplando as chamadas HTTP e a geração do PDF. Mantenha o timeout HTTP fiscal de 20 segundos ou ajuste esse orçamento ao alterá-lo. Para verificar, execute `docker compose exec horizon php artisan horizon:status`. Seu painel e APIs em `/horizon` exigem token OAuth2 `admin:manage`, conta liberada e Administrador com página `manutencao`, inclusive em ambiente local. A collection inclui uma chamada às estatísticas com Bearer token. Não há acesso público por estar em desenvolvimento.

## Cadastro do provedor

1. Crie a conta e confirme o e-mail no Fiscal Contora.
2. Gere uma chave de **homologação**, cadastre a empresa nesse ambiente e configure o certificado A1 no painel do provedor. O certificado e sua senha não são enviados para esta API Laravel.
3. Configure localmente `FISCAL_CONTORA_TOKEN`, `FISCAL_CONTORA_CNPJ` (14 dígitos), `FISCAL_CONTORA_AMBIENTE=homologacao` e opcionalmente `FISCAL_CONTORA_TIMEOUT=20`. Não versione o token. Execute `docker compose exec php php artisan config:clear` após editar o `.env` se houver configuração em cache.
4. Aplique as migrations pendentes com `docker compose exec php php artisan migrate`.
5. Importe novamente a collection Postman e configure `adminToken`, `produtoId` e `contoraIdempotencyKey`.

O backend verifica a identidade da chave via `/me` antes das operações remotas. Uma chave de produção ou configuração de produção é recusada. Não há contratação automática de plano ou recarga.

## Fluxo pela nossa API

Use um Administrador com página `contabilidade` e token OAuth2 com escopo `admin:manage`:

| Método | Endpoint | Resultado |
|---|---|---|
| POST | `/api/admin/notas-fiscais/emitir` | Persiste a solicitação, cria o rascunho e solicita autorização; responde 202, sem garantir autorização final. |
| GET | `/api/admin/notas-fiscais/{id}` | Consulta os dados locais e o status já conciliado. |
| POST | `/api/admin/notas-fiscais/{id}/sincronizar` | Consulta o provedor; se autorizada, baixa o XML e gera o PDF localmente. |
| GET | `/api/admin/notas-fiscais/{id}/xml` | Download privado do XML autorizado. |
| GET | `/api/admin/notas-fiscais/{id}/danfe` | Download privado do DANFE em PDF. |
| GET | `/api/logistica/notas-fiscais/{id}/danfe` | Mesmo PDF, exigindo perfil Logística e escopo `logistics:manage`. |

Os downloads bem-sucedidos retornam arquivo binário com Content-Disposition; erros continuam usando `{status, statusCode, data}`. Demais respostas são JSON com esse envelope.

Payload completo de exemplo: [exemplos/contora-emissao.json](exemplos/contora-emissao.json). Os documentos e a tributação do exemplo são fictícios; confirme NCM, CFOP, regime e tributos com a Contabilidade. `produto_id` deve existir no banco local. Código e descrição são capturados do catálogo no primeiro envio, e o destinatário fiscal precisa ser informado porque o cadastro atual de usuário não contém todos os dados tributários.

O recorte inicial aceita os campos tributários ICMS (`code`, `origin`, `aliquot`), PIS, COFINS e IPI (`code`, `aliquot`). Frete, descontos, ST, DIFAL e demais grupos fiscais ainda exigem ampliação do contrato antes de usar esses cenários. O provedor valida os totais calculados e a soma de pagamentos. Finalidades complementar e devolução exigem `chaves_referenciadas` com as chaves de 44 dígitos. O `pedido_id`, opcional, só pode acompanhar uma venda e seus produtos/quantidades são validados contra o pedido.

## Retentativas e dados

Reutilize a mesma `idempotency_key` e o mesmo payload após timeout. A solicitação local e o identificador remoto são persistidos; repetir o envio não cria outra nota local. A integração consulta o status antes de transmitir novamente e não reenvia notas já enfileiradas. Outra chave para a mesma identidade fiscal (emitente, ambiente, série e número) retorna 409. Se não foi possível obter o ID remoto em até 23 horas, a emissão é bloqueada para reconciliação manual, pois a idempotência remota é garantida por 24 horas.

Não altere o payload usando a mesma chave. Corrigir uma nota rejeitada, reenviar com dados corrigidos, cancelar notas e processar webhooks serão fluxos próprios. O fluxo de pedidos já acompanha a autorização pela fila; as chamadas administrativas avulsas ainda permitem sincronização manual com intervalos crescentes. Não faça polling contínuo pelo frontend.

O snapshot enviado ao provedor fica cifrado no banco com a chave Laravel e oculto nas respostas. XML e PDF ficam em `storage/app/private/fiscal/homologacao/{id}`. O backend verifica emitente, ambiente, chave e protocolo do XML antes de gerar o PDF; não usa URLs de download arbitrárias retornadas pelo provedor. A autorização da nota não é simulada: vem da consulta ao Fiscal Contora.

## Referências

- [Documentação e onboarding](https://fiscal.contora.com.br/documentacao)
- [Contrato NF-e](https://fiscal.contora.com.br/documentacao/nfe)
- [Consulta de status e artefatos](https://fiscal.contora.com.br/documentacao/status)
- [Idempotência](https://fiscal.contora.com.br/documentacao/resiliencia)

Os testes automatizados usam `Http::fake`, banco SQLite em memória e armazenamento temporário. Não fazem emissão real nem consomem eventos da conta.
