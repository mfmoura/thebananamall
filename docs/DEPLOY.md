# Git Flow e publicação

`main` contém a versão aprovada. `develop` integra a próxima versão e publica na
Oracle de homologação. Crie `feature/nome` a partir de `develop`; integre por PR.
Crie `release/x.y.z` de `develop`, estabilize e abra PR para `main`. Após aprovar,
marque `vX.Y.Z` no commit da `main` e integre a release de volta em `develop`.
Correções urgentes usam `hotfix/nome`, partindo de `main`, e voltam para ambas.
Não reescreva o histórico dessas branches.

O check obrigatório é **Testes e build**, do workflow **Pipeline**. Ele executa
PHPUnit em SQLite em memória, testes de frontend, build Vite e verificações dos
scripts de publicação. Não executa a homologação financeira em `tests/Integration`.
Testes em SQLite não substituem validação de migrations e concorrência em PostgreSQL.

## Ambientes GitHub

Configure `staging` permitindo somente `develop`, e `production` somente `main`.
Cada ambiente recebe secrets `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`,
`DEPLOY_PATH`, `DEPLOY_KEY` (chave dedicada ao pipeline) e `DEPLOY_KNOWN_HOSTS`
(chave do host conferida previamente). Não use a chave pessoal nem desabilite a
verificação do host SSH. O usuário precisa executar Docker por sudo sem interação.
Isso concede privilégios equivalentes aos do administrador do host; mantenha
esses secrets restritos às branches autorizadas.

A chave dedicada deve ter `restrict` e comando forçado em `authorized_keys`,
apontando para `/.deploy/ssh-entrypoint.sh` dentro da pasta da aplicação (use o
caminho absoluto completo, não a raiz `/`). O entrypoint aceita somente `status`,
`upload SHA CHECKSUM` (pacote em stdin, até 250 MiB) e `deploy SHA`; não oferece
shell, SFTP, SCP nem encaminhamento de portas. `DEPLOY_PATH` documenta o destino;
o caminho efetivo é fixado pelo comando forçado no servidor.

As variáveis **do repositório** habilitam a publicação:

- `STAGING_DEPLOY_ENABLED=true`: publica `develop` na Oracle atual.
- `PRODUCTION_DEPLOY_ENABLED=false`: produção permanece desligada até configurar
  o servidor oficial, seus secrets e os testes de aceitação. Depois altere para `true`.

Se o plano GitHub não oferecer ambientes/proteção para repositórios privados,
é necessário habilitar um plano compatível para usar essa configuração. Não
substitua silenciosamente a proteção por secrets acessíveis a qualquer branch.

## Preparação do servidor

Requer a aplicação Docker Compose já instalada, `.env`, `storage`, vendor, os
serviços `php`, `nginx`, `horizon`, `scheduler`, `db` e o arquivo de adaptações
`docker-compose.override.yaml`. Instale `curl`, `tar`, `flock` e `realpath` no host.
Faça o provisionamento uma vez, com o mesmo usuário dono da aplicação:

```bash
bash scripts/deploy/provision.sh /home/ubuntu/lojavirtuallcb https://seu-dominio
```

O script preserva a versão atual como `legacy-*`, prepara `.deploy/current` e
acrescenta `COMPOSE_FILE` ao `.env` para incluir o override dos volumes de release.
A origem usada na verificação fica em `.deploy/server.conf`, fora do Git.
Não mude credenciais, chaves, URLs ou serviços de dados para publicar código.

## Funcionamento

Após os checks, o pipeline monta um pacote sem `.env`, dados de `storage` ou
configurações Docker. O pacote inclui `vendor` sem dependências de desenvolvimento
e assets compilados, recebe o SHA do commit e um checksum SHA-256 e é transferido
por SSH. Não se executa `git pull` nem build de frontend na Oracle.

Cada publicação usa uma pasta em `.deploy/releases`, faz backup PostgreSQL em
formato custom em `.deploy/backups` e troca o link `.deploy/current`. Antes da
troca, entra em manutenção e encerra Horizon e scheduler com o prazo configurado.
Recria PHP/Nginx, limpa caches, descobre packages e executa `migrate --force`.
Após liberar manutenção, verifica login, asset principal e recusa da sessão
anônima. Reinicia os workers e verifica o Horizon. A versão aprovada fica em
`.deploy/deployed-version`. A publicação tem uma breve janela de indisponibilidade.

Pacotes, backups e releases anteriores não são apagados automaticamente. Monitore
o espaço em disco. O `.deploy` fica com acesso restrito e fora do diretório público.
O `.env` e o `storage` originais são montados em todas as versões; notas, pedidos,
uploads, chaves OAuth e registros excluídos logicamente permanecem preservados.

Alterações em Dockerfile, Compose e configurações `.docker` não são aplicadas pelo
pacote. Exigem atualização planejada da infraestrutura e compatibilidade do PHP.

## Falhas e recuperação

Uma falha antes das migrations ou após migrations concluídas restaura o link e
os containers da versão anterior. **O banco não é revertido automaticamente**:
as migrations precisam ser aditivas e compatíveis com a versão anterior. Se uma
migration falhar, manutenção e filas paradas são preservadas para diagnóstico.
Não execute rollback destrutivo nem restaure dumps sobre dados recentes por rotina.

Para retornar manualmente a uma release compatível, suspenda novos deployments,
entre em manutenção, pare workers, troque `.deploy/current` pelo caminho absoluto
da release desejada e recrie PHP/Nginx com `docker compose up -d --no-deps
--force-recreate php nginx`. Limpe os caches, libere a manutenção e só retome
Horizon/scheduler após verificar a aplicação. Não use `docker compose down -v`.

Para repetir uma publicação, use **Re-run all jobs** no GitHub. O mesmo commit
gera outra pasta de release e outro backup, sem sobrescrever a versão anterior.

Referências: [ambientes GitHub](https://docs.github.com/en/actions/concepts/workflows-and-actions/deployment-environments)
e [sintaxe dos workflows](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax).
