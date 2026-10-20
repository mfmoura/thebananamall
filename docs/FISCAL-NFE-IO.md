# NFE.io — NF-e de homologação

A emissão passa a ser NFE.io. O contrato público dos endpoints permanece em português, com os mesmos caminhos e permissões. O backend converte o snapshot para a API NF-e v2; o navegador nunca recebe a chave NFE.io. O suporte operacional à Contora foi retirado por decisão do responsável. Registros antigos permanecem preservados no banco, fora dos fluxos de emissão e sincronização atuais.

## Configuração

```dotenv
NFE_IO_TOKEN=
NFE_IO_COMPANY_ID=
NFE_IO_STATE_TAX_ID=
NFE_IO_CNPJ=
NFE_IO_UF_EMITENTE=SP
NFE_IO_SERIE=1
NFE_IO_AMBIENTE=homologacao
NFE_IO_TIMEOUT=20
NFE_IO_DOWNLOAD_HOSTS=
```

Use a chave de **Nota Fiscal**, não a chave de consultas de dados. O nome anterior `NFE-IO_TOKEN` é inválido para o dotenv do Laravel; use `NFE_IO_TOKEN`.

Cadastre a empresa, seu certificado A1 e uma inscrição estadual de tipo `nFe`, ambiente `Test`, UF e série correspondentes. Homologação de NF-e também exige certificado A1: ela usa a SEFAZ de testes. O backend consulta a empresa e a IE antes da emissão e bloqueia configuração de produção, CNPJ, UF, série ou tipo divergentes. A rota de emissão informa explicitamente a IE, evitando a seleção implícita da primeira inscrição da empresa.

Em 20/10/2026, a consulta autenticada do ambiente local retornou HTTP 200, uma empresa e nenhuma inscrição NF-e Test ou certificado. Os identificadores da empresa foram preenchidos somente no `.env` local. Não foi emitida nota nem alterado cadastro remoto. Falta completar o cadastro na plataforma e preencher `NFE_IO_STATE_TAX_ID`.

O endpoint XML retorna `{ "uri": "https://..." }` com link temporário. Configure `NFE_IO_DOWNLOAD_HOSTS` com o **host exato** do armazenamento fornecido pela NFE.io (lista separada por vírgulas). Não use curingas ou domínios arbitrários. O download usa uma chamada sem a credencial de API e sem redirects. Até configurar esse host, o download fica bloqueado; não há liberação de DANFE sem XML validado.

Após alterar configuração, execute `docker compose exec php php artisan config:clear` e reinicie os workers com `docker compose restart horizon`. Mantenha o Horizon ativo.

## Fluxo e idempotência

O checkout apenas persiste o acompanhamento cifrado. O pagamento confirmado pelo backend libera o job na fila Redis `fiscal`, após commit. Preços, quantidades e tributação vêm do snapshot do pedido. A série e a numeração local são preservadas; a sequência considera também notas logicamente excluídas e notas de outros provedores para o mesmo emitente e ambiente.

Cada POST de emissão na NFE.io cria uma nova nota: **não existe chave de idempotência remota para essa operação**. A `idempotency_key` da nossa API continua obrigatória e deduplica localmente. O backend persiste a intenção de envio antes da chamada e armazena o ID retornado, retomando consultas sempre sobre ele. Não há chamada de rascunho ou dispatch.

Em timeout, queda do worker ou resposta sem ID válido, o resultado é incerto. Repetir a mesma solicitação retorna 409, sem outro POST remoto. O operador deve localizar no painel/listagem da NFE.io a nota pelo emitente, ambiente, série, número e dados do snapshot, conferir sua identidade e reconciliar a `referencia_provedor` na nota local original por procedimento administrativo controlado. Depois disso, sincronizar o registro original retoma o processamento. Se nada foi localizado, a ausência na listagem pode ser temporária: não criar outra chave nem limpar `emissao_enviada` automaticamente. Ainda não existe endpoint para essa reconciliação manual.

`Created`, `Processing` e `IssuedContingency` permanecem em processamento. `Issued` permite buscar o XML autorizado, conferir chave, protocolo, CNPJ, ambiente, série e número e gerar o DANFE privado com NFePHP. Somente então a nota fica autorizada localmente. `Error`/`IssueDenied` não liberam documentos. Homologação não movimenta estoque. Consultas posteriores não fazem regredir uma autorização ou cancelamento.

## Operação pela API e Postman

A pasta **NFE.io (homologacao)** usa `adminToken` (Contabilidade, escopo `admin:manage`), `produtoId`, `nfeIoIdempotencyKey` e `notaFiscalNfeIoId`. Exemplo: [exemplos/nfeio-emissao.json](exemplos/nfeio-emissao.json). Não reutilize os números fictícios sem conferir sua sequência real.

| Método | Endpoint | Uso |
| --- | --- | --- |
| POST | `/api/admin/notas-fiscais/emitir` | Aceita a solicitação; 202 não comprova autorização. |
| GET | `/api/admin/notas-fiscais/{id}` | Consulta local. |
| POST | `/api/admin/notas-fiscais/{id}/sincronizar` | Consulta a NFE.io e disponibiliza arquivos autorizados. |
| GET | `/api/admin/notas-fiscais/{id}/xml` | XML privado. |
| GET | `/api/admin/notas-fiscais/{id}/danfe` | DANFE privado. |
| GET | `/api/logistica/notas-fiscais/{id}/danfe` | Exige Logística e `logistics:manage`. |

O cliente continua consultando seu próprio pedido e baixando `/api/pedidos/{id}/danfe` após conclusão. Autorização e envelopes não foram alterados.

## Limites deste recorte

O frete é rateado entre os itens em centavos, preservando o total. Os tributos atuais são traduzidos para `tax`, sem acionar `taxDetermination` ou contratar o motor de cálculo. Há suporte a ICMS 00 com alíquota e a códigos simples sem tributação (40/41 e CSOSN 102/103/300/400), e a PIS/COFINS 01/02 com alíquota ou 04–09. Campos não representados pelo cadastro atual, como ST, redução e IPI, são recusados antes de enviar, em vez de presumidos. Esses casos exigem ampliação do contrato e definição contábil. Ajuste e devolução também exigem ampliar os dados de referência por item; a NFE.io aceita inicialmente apenas finalidade normal ou complementar neste adaptador.

Pagamento com cartão informa integração com o comércio eletrônico; dados adicionais de adquirente/autorização e exigências da UF ainda precisam de validação em homologação. Regras de IBS/CBS, DIFAL e demais cenários tributários não estão implementadas neste recorte. Não habilitar produção. A validação real depende dos cadastros e da parametrização da Contabilidade.

Validação local em 20/10/2026: `NfeIoTest`, `PedidoFiscalFilaTest` e `FreteTest`, com HTTP simulado e SQLite em memória: **32 testes, 160 asserções, todos aprovados**. Formatação Pint aprovada nos arquivos fiscais alterados. A execução usou root no contêiner devido à propriedade dos diretórios temporários de teste; nenhuma permissão de infraestrutura foi modificada. Essa validação não comprova emissão na SEFAZ: a conta ainda exige certificado A1, IE NF-e Test e configuração do host do XML.

## Referências oficiais

- [Planos e preços para emissão de NF-e](https://nfe.io/precos/emissao-nfe/)
- [Primeiros passos, certificado e ambiente na IE](https://nfe.io/docs/documentacao/nota-fiscal-produto-eletronica/integracao-api/primeiros-passos/)
- [Emissão NF-e v2](https://nfe.io/docs/desenvolvedores/rest-api/nota-fiscal-de-produto-v2/emitir-uma-nota-fiscal-eletronica-nfe/)
- [Processamento e idempotência, seção 10.2](https://nfe.io/docs/emissao-nfe-nfce-processamento-resiliencia-e-contingencia-en/)
- [Consulta e URLs temporárias de XML/DANFE](https://nfe.io/docs/documentacao/nota-fiscal-produto-eletronica/integracao-api/como-consultar-nota-fiscal-eletronica/)
- [Layout, tributos e enums](https://nfe.io/docs/documentacao/reforma-tributaria/conceitos-funcionais/nota-fiscal-de-produto/documentation-nfe-layout-rtc/)
