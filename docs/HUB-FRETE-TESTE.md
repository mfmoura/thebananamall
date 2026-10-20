# Teste de cotação — Hub do Desenvolvedor

Executado em 19/10/2026 contra `https://ws.hubdodesenvolvedor.com.br/v2/correios/`, serviço `calculoFrete`. Origem informada pelo responsável: **03044000**. Sem criação de pedidos, compra de postagem ou alteração do checkout.

Como peso e dimensões não foram informados, foi utilizada uma caixa de exemplo de **10 cm de altura × 15 cm de largura × 20 cm de comprimento**, representando **500 g**, formato enviado `1`. Códigos aceitos nesta execução: PAC `04510` e SEDEX `04014`.

## Resultado da matriz

Todas as dez consultas retornaram HTTP 200, `status: true`, `return: OK` e entrega domiciliar.

| Destino | CEP | PAC | Prazo PAC | SEDEX | Prazo SEDEX |
|---|---|---:|---|---:|---|
| São Paulo/SP | 01001000 | R$ 25,80 | 5 dias úteis | R$ 27,60 | 1 dia útil |
| Rio de Janeiro/RJ | 20040002 | R$ 30,50 | 5 dias úteis | R$ 46,80 | 1 dia útil |
| Uberlândia/MG | 38400100 | R$ 36,00 | 5 dias úteis | R$ 71,40 | 1 dia útil |
| Londrina/PR | 86010010 | R$ 36,00 | 6 dias úteis | R$ 71,40 | 2 dias úteis |
| Blumenau/SC | 89010025 | R$ 36,00 | 6 dias úteis | R$ 71,40 | 2 dias úteis |

O provedor acrescenta o **dia da postagem** a todos os prazos. Valores são os retornados nessa consulta e não uma tabela comercial permanente. Tempos da matriz: entre 2.826 e 9.292 ms.

## Divergência do parâmetro de peso

O exemplo fornecido indicava gramas. Porém, `peso=500` retornou HTTP 200 com erro de negócio `status: false`, `return: NOK`, código ERP-036, peso/valor excedendo o limite estadual. O mesmo erro ocorreu com código de serviço `03298`.

Com os demais parâmetros iguais, `peso=0.5` retornou cotação válida. A matriz utilizou esse valor. O comportamento é compatível com peso em quilogramas neste endpoint; a resposta não ecoa o peso tarifado. Confirmar o contrato/unidade do Hub antes de transformar o diagnóstico em integração de produção. HTTP 200 sozinho não indica sucesso da cotação.

Resposta observada (resumida):

```json
{
  "status": true,
  "return": "OK",
  "result": {
    "servico": "PAC",
    "prazo_de_entrega": "Dia da Postagem + 5 dias úteis",
    "entrega": "Entrega domiciliar",
    "preco_servico": "R$ 25,80",
    "valor_total": "R$ 25,80"
  }
}
```

Preço e prazo chegam como textos, inclusive moeda e separadores brasileiros. Uma integração futura deverá normalizá-los e tratar erros de negócio, timeouts e respostas sem JSON. Este teste comprova cotação; não testa emissão de etiqueta ou rastreamento.

## Clientes fictícios criados

O seeder dedicado `FreteTesteSeeder` confirmou os cinco CEPs pelo ViaCEP e criou contas Cliente, endereços e dados fiscais cifrados. Faker `pt_BR` gera nome, CPF sintético e número de endereço. Logradouro, cidade, UF, bairro e código IBGE vêm do CEP real; não representam identidade ou residência de pessoa real.

Logins: `frete-teste-sao-paulo`, `frete-teste-rio-de-janeiro`, `frete-teste-uberlandia`, `frete-teste-londrina`, `frete-teste-blumenau`. E-mails usam o domínio reservado `example.test`. Senha exclusiva desses cadastros de desenvolvimento: `FreteDeTeste@2026`, armazenada pelo hash do Laravel. Contas confirmadas localmente, sem envio de e-mail. O seeder só executa em `local`/`testing`, usa transação e reutiliza os registros ativos nas próximas execuções, preservando os dados Faker já criados. Não foi incluído no seeder geral.

## Repetir

Configure `HUB_DESENVOLVEDOR_TOKEN` no `.env` local. O token não integra este documento nem o código; o diagnóstico não inicializa Laravel/Telescope, não segue redirecionamentos e não registra URLs autenticadas.

```sh
# Cinco Clientes locais de teste
docker compose exec php php artisan db:seed --class=FreteTesteSeeder

# Uma consulta inicial (São Paulo, PAC)
docker compose exec -T php php scripts/testar-hub-frete.php --primeira

# Dez consultas: os cinco destinos, PAC e SEDEX
docker compose exec -T php php scripts/testar-hub-frete.php
```

O script imprime uma linha JSON por consulta, com tempo, HTTP e retorno do provedor, removendo o token se ele aparecer no corpo. Para no primeiro erro. Parâmetros de diagnóstico opcionais: `--peso=0.5`, `--tipo-servico=04014`, `--formato=1`. Uma execução completa faz dez consultas reais ao serviço.
