# Comparação de filesystem — 17/10/2026

## Resultado

O bootstrap Laravel CLI foi aproximadamente **25,7 vezes mais rápido** no filesystem Linux do container, comparando a mediana de três execuções em cada ambiente.

| Inicialização completa | Volume Windows | Filesystem Linux |
| --- | ---: | ---: |
| Amostra 1 | 10.385,33 ms | 560,43 ms |
| Amostra 2 | 10.459,06 ms | 406,96 ms |
| Amostra 3 | 13.037,94 ms | 251,58 ms |
| **Mediana** | **10.459,06 ms** | **406,96 ms** |

Todas as execuções carregaram **563 arquivos** e atingiram **22 MiB** de memória PHP. Medianas do autoload: Windows 1.207,64 ms, Linux 54,73 ms. Medianas da etapa posterior de bootstrap: Windows 9.263,37 ms, Linux 352,22 ms.

## Método

- Mesmo container PHP, imagem, CPU, limite de RAM e código, incluindo o mesmo vendor e autoload otimizado.
- Original em `/var/www/html`, montado a partir do disco Windows; cópia em um diretório temporário de `/tmp`, no filesystem Linux do container.
- A cópia incluiu vendor, app, bootstrap, config, routes, resources, lang, composer.json e artisan. Nenhum `.env` foi copiado: ambos os processos leram o mesmo arquivo de ambiente original.
- PHP CLI com `opcache.enable_cli=0` e `pcov.enabled=0` explicitamente nas duas condições; cada amostra usou um processo novo.
- Ordem: Windows, Linux, Linux, Windows, Windows, Linux. O tempo do comando Docker, da preparação e da cópia não entra nas medições.
- O script mede autoload e bootstrap do Console Kernel, sem executar comandos de negócio nem requisições HTTP. A instrumentação está em `scripts/comparar-filesystem.php`.
- A cópia temporária e o pacote de transferência foram removidos ao terminar. O projeto e seus volumes não foram migrados.

## Interpretação

O armazenamento compartilhado Windows/Docker contribui fortemente para o custo de inicialização observado. O mesmo código carregando o mesmo número de arquivos no Linux usou a mesma memória e terminou muito antes.

Esta é uma comparação CLI, não um teste de carga ou medição ponta a ponta da loja. PHP-FPM tem OPcache persistente; rede, SQL, Redis, filas e renderização não foram comparados aqui. Portanto, não se deve prometer ganho de 25,7 vezes no tempo total de uma página. São três amostras por condição, com caches do sistema e carga de fundo variáveis.

O próximo ajuste recomendado é manter o projeto no filesystem Linux de uma distribuição WSL2 e abrir o editor pelo WSL, ou comparar vendor/node_modules em volumes Docker. O resultado no WSL2 precisa ser validado após a mudança: este teste utilizou o filesystem Linux do container, e não uma cópia do projeto em uma distribuição WSL2 do usuário.

Para repetir após preparar outra cópia do código dentro do mesmo container:

```sh
docker compose exec php php -d opcache.enable_cli=0 -d pcov.enabled=0 scripts/comparar-filesystem.php /var/www/html
docker compose exec php php -d opcache.enable_cli=0 -d pcov.enabled=0 scripts/comparar-filesystem.php /caminho/linux/da/copia
```

A cópia precisa de `storage/framework/views`, `storage/framework/cache/data` e `storage/logs` graváveis, além das dependências já instaladas.
