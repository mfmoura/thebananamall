# Loja Virtual LCB

API da loja virtual de artigos da Liga Comunista Brasileira (LCB).

## Histórico do projeto

### Sprint inicial — 15/10/2026

- Modelagem inicial das classes e dos casos de uso.
- Definição dos requisitos funcionais e apresentação aos stakeholders.
- Configuração das ferramentas de gestão e modelagem do banco de dados.

### Desenvolvimento — 16/10/2026

- Implementação da API Laravel com PostgreSQL e Redis, seguindo a separação entre controllers, FormRequests, services e models.
- Implementação dos perfis de usuário Cliente, Administrador e Logística, autenticação por token e endereços.
- Implementação de carrinho no Redis e checkout persistido no PostgreSQL, com itens de pedido que preservam o preço usado na compra.
- Implementação de catálogo de produtos, categorias, imagens, preços históricos e movimentações de estoque por entradas, saídas e estornos.
- Implementação dos models e migrations para notas fiscais e itens de nota, com enums de status e operação. A emissão ainda depende de integração com provedor fiscal.
- Atualização da collection e das instruções de testes manuais no Postman.

O fluxo de checkout cria o pedido como `aguardando_pagamento` e registra uma saída de estoque. A cobrança por gateway ainda não está integrada. Também faltam emissão e consulta de DANFE, operações de expedição para o perfil Logística e cálculo de disponibilidade descontando reservas de carrinhos ativos. Consulte [TODO.md](TODO.md) para as etapas pendentes.

## Desenvolvimento local

Inicie os containers e aplique as migrations e dados de demonstração:

```bash
docker compose up -d
docker compose exec php php artisan migrate --seed
```

As instruções e contas locais de teste estão em [`postman/README.md`](postman/README.md); a collection para importar está em [`postman/LojaVirtualLCB.postman_collection.json`](postman/LojaVirtualLCB.postman_collection.json).

## Documentação base

Diagrama de classes e casos de uso:

https://lucid.app/lucidchart/bc14f3ed-2cf3-4dac-ba61-31c056992e16/edit?viewport_loc=-652%2C-104%2C2365%2C1227%2CE9IDngY-3BPj&invitationId=inv_36efa41a-d4c7-48a9-aee8-d744df83e09e
