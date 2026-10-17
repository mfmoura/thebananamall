# Diagnóstico de desempenho — 17/10/2026

## Repetir medições

```sh
docker compose exec node npm run perf
docker compose exec node npm run build
```

`perf` faz três consultas sequenciais por recurso, conserva cookies de visitante em memória e mede status, TTFB, duração e bytes. Fora do Docker, configure `PERF_URL=http://localhost` e `PERF_VITE_URL=http://localhost:5173`. O catálogo pode criar a identidade de visitante usada pelo fluxo normal da loja. Não executa compras nem usa contas autenticadas.

O middleware `MedirDesempenhoLocal` acrescenta `Server-Timing` somente em `APP_ENV=local`: `bootstrap` (entrada PHP até o middleware global), `application` (processamento posterior) e `sql` (tempo acumulado das consultas posteriores, já incluído em application). Terminação da requisição e gravação do Telescope não entram nessas parcelas.

## Medições iniciais

Três amostras HTTP por recurso, pelo Node dentro do Docker. Houve instalação Composer concorrente: estes valores são diagnóstico preliminar sob carga, não um benchmark controlado.

| Recurso | Tempo observado |
| --- | --- |
| HTML Laravel | 1,96–4,95 s |
| Sessão BFF | 4,04–10,01 s |
| Catálogo BFF, 5 produtos | 3,99–12,69 s |
| Entrada React Vite | 6–18 ms |
| CSS Vite, primeira transformação | 5,97 s |
| CSS Vite, aquecido | 10–18 ms |

No catálogo, bootstrap respondeu por 2,66–9,61 s, aplicação por 1,30–3,04 s e SQL por 79–101 ms. Isso localiza o maior custo antes da lógica de catálogo; não demonstra que o bloqueio de estoque seja o gargalo. Acesso a arquivos/autoload no volume Windows/Docker é uma hipótese forte, ainda sem comparação com volume Linux.

Build Vite: 25,11 s, 117 módulos; JavaScript 347 KB (113 KB gzip). O tamanho do bundle e a resposta do módulo de entrada não medem renderização, dependências carregadas no navegador nem Core Web Vitals. Para isso, usar a aba Performance do navegador e React Profiler.

## Telescope

Disponível em `http://localhost/telescope` somente no ambiente local e com hostname local. Instalado como dependência de desenvolvimento; produção não registra seus providers. Coleta somente duração das requisições e consultas SQL com strings ocultas, sem payloads, respostas, cookies, sessões ou identificação do usuário. Watchers de e-mail, notificações e Redis ficam desligados. Há limpeza diária dos registros com mais de 24 horas.

Para comparar sem o custo do coletor, use `TELESCOPE_RECORDING=false` no `.env` local e limpe o cache de configuração. Telescope adiciona custo e não substitui uma medição sem instrumentação.

Referência: [instalação e configuração oficiais do Telescope](https://laravel.com/docs/telescope).

## Confirmação com Telescope e volume

O resumo do Telescope registrou 12 consultas por página de catálogo, com 53–111 ms de SQL. Sob instrumentação e outras medições concorrentes, as requisições chegaram a 7,8–22,7 s. Esses valores não permitem calcular isoladamente o custo do Telescope. Após coletar o diagnóstico, `TELESCOPE_RECORDING=false` foi configurado no ambiente local para evitar custo permanente. Para coletar novamente, altere `TELESCOPE_RECORDING` para `true`; o painel continua acessível com a coleta desligada.

100 leituras sequenciais do mesmo arquivo pelo Node: 731 ms no volume do projeto e 138 ms em arquivo temporário no disco do container (5,3 vezes). É uma evidência de latência do volume; não mede diretamente o tempo total de autoload PHP.

Resumo SQL dos últimos registros: `docker compose exec php php artisan desempenho:resumo`. Para uma rodada curta: `docker compose exec -e PERF_SAMPLES=1 node npm run perf`.

Próximo ajuste recomendado: comparar o projeto em filesystem Linux/WSL2 ou colocar `vendor` e `node_modules` em volumes Docker, medindo antes/depois sem instalações concorrentes. Essa mudança de armazenamento não foi aplicada neste diagnóstico.

`TELESCOPE_ENABLED=true` mantém as rotas do painel disponíveis; `TELESCOPE_RECORDING` controla os dois watchers de diagnóstico. Desligar `TELESCOPE_ENABLED` desativa também o painel.

## Rodada final

Após concluir a instalação e restaurar autoload otimizado, com coleta desligada: catálogo 6.422 ms (bootstrap 3.428 ms, application 2.950 ms, SQL 124 ms); sessão BFF 2.981 ms; entrada React Vite 10 ms; CSS em transformação 6.189 ms. Uma amostra por recurso, sem avaliação de renderização do navegador. Leitura de arquivos: 1.511 ms no volume contra 196 ms no temporário. Painel validado com HTTP 200 usando Host localhost. O diagnóstico identifica o custo do backend e aponta armazenamento/autoload como candidato para a próxima comparação; não prova causalidade exclusiva do volume.

## Renderização durante testes manuais

No Vite de desenvolvimento, o React Profiler envia pelo WebSocket do HMR contagens e duração agregada dos commits a cada 2 segundos. PerformanceObserver informa long tasks, LCP, mudanças de layout sem interação recente e duração HTTP do cadastro. Nenhum campo, senha, URL ou parâmetro é transmitido. Acompanhar: `docker compose logs --follow node` e procurar `[desempenho]`. A coleta depende de recarregar a página com Vite/HMR conectado; não mede produção. StrictMode permanece ativo, portanto os resultados são de desenvolvimento. O evento cadastro-http mede a requisição inteira (inclusive backend), não apenas renderização. LCP se refere ao carregamento do documento, não a cada navegação React. Fontes e imagens também podem afetá-lo.

### Fluxo manual de cadastro observado

Durante o teste manual, o Vite recebeu LCP inicial de 24.038 ms; commits React de 1–47 ms; requisição HTTP de cadastro de 19.808 ms; commits após essa requisição somando 31 ms (máximo 24 ms). Não foram capturados dados preenchidos nem status da resposta. LCP e métricas podem pertencer a mais de uma aba conectada ao mesmo Vite; não é uma comparação de produção nem permite atribuir sozinho a causa da espera. Nesse envio, a duração HTTP foi muito maior que a renderização. O código de cadastro faz consulta de senha comprometida e envio da confirmação por e-mail antes de responder; ainda é necessário medir cada etapa para atribuir a latência individualmente.

Atualização após o diagnóstico: consulta Pwned Passwords removida por decisão do projeto. As medições anteriores ao ajuste ainda incluem essa etapa; o impacto da remoção não foi medido.

## Login: diagnóstico de 504 e validação em navegador

Teste em Edge headless usando formulário React e conta isolada. O log original mostrou POST `/oauth/login` 200 seguido de timeout em GET `/oauth/authorize`. A chave privada estava como root:root/600 e não podia ser lida pelo PHP (UID 1000). Após corrigir o proprietário, apareceu a ausência do contrato `AuthorizationViewResponse`, resolvida pelo registro de `Passport::authorizationView`. Na consulta autenticada, a biblioteca rejeitou a chave pública com permissão 644; ambas passaram a 1000:1000/600, sem regeneração.

Validação final: autorização redirecionou para `/bff/callback`, depois `/oauth/callback`; sessão e `/bff/api/me` retornaram 200, mesclagem do carrinho retornou 200 e a interface abriu `/conta` com título “Minha conta”. Carrinho ausente retornou 404, tratado pelo frontend como vazio. A conta de diagnóstico foi excluída logicamente e seus tokens revogados ao fim. A captura local fica em `storage/app/diagnostico-login.png` (ignorada pelo Git). A lentidão geral do ambiente continua sendo uma questão distinta; o timeout por erro de configuração foi corrigido, sem aumentar o timeout do Nginx.
## Edge: recursos, sessão e e-mails em fila

Na navegação medida antes dos ajustes, HTML TTFB 3.926 ms (bootstrap 2.773 ms, aplicação 1.131 ms e SQL 13 ms); DOM pronto em 4.160 ms. Havia duas consultas de sessão e duas de carrinho, iniciadas pelo efeito duplicado do StrictMode; chamadas de sessão levaram 6.374 e 9.367 ms. A inicialização do provider agora é única por montagem, mantendo StrictMode ativo. Na rodada seguinte foi observada somente uma chamada por recurso; seus tempos variaram (sessão 12.252 ms, carrinho 5.045 ms), portanto não se atribui redução global de latência à deduplicação.

Outro carregamento registrou LCP 5.408 ms, CLS 0,0021 e nenhuma long task no buffer da página. Mesmo no cadastro, módulos Checkout/Pedidos/Carrinho eram buscados, com até 1.520 ms por módulo em desenvolvimento. Essas páginas passaram a React.lazy/Suspense com skeleton; cadastro mantém o módulo de autenticação e não importa essas páginas. Tailwind passou a procurar classes somente em resources/js e resources/views, evitando varredura do restante do projeto e geração de utilitários a partir de textos de backend/documentação.

E-mails de confirmação, troca, recuperação e alerta agora usam Redis/Horizon na fila emails, após commit, com payload cifrado e retentativas. SMTP não bloqueia a requisição HTTP. O TTL continua contando desde a solicitação; shouldSend descarta tokens vencidos/consumidos e contas excluídas. MAIL_TIMEOUT limita SMTP a 20 s. O Horizon foi reiniciado para carregar os supervisores atuais e confirmou status ativo. Um job de diagnóstico com link inválido foi enfileirado e concluído em aproximadamente 1 s sem enviar e-mail externo. Isso valida o consumo e descarte; não é prova de entrega SMTP de uma mensagem real.

As medições ocorreram em desenvolvimento, com cache e carga variáveis, incluindo builds em algumas rodadas; não são benchmark controlado nem eliminam o gargalo de inicialização/arquivos do backend já identificado.
## Memória e configuração dos containers

Docker disponibilizava 8,67 GiB; o projeto consumia cerca de 670 MiB na amostra inicial, sem OOMKilled nem reinícios por memória nos serviços inspecionados. Não havia limites por container, Redis usava maxmemory=0 e PCOV estava ativo em todas as requisições, incluindo vendor.

| Serviço | Limite de RAM |
| --- | --- |
| PHP-FPM | 1536 MiB |
| Horizon | 1536 MiB |
| Node | 1536 MiB |
| PostgreSQL | 1024 MiB |
| Redis | 768 MiB |
| Scheduler | 512 MiB |
| Nginx | 128 MiB |
| ngrok (perfil opcional) | 128 MiB |

São tetos, não reservas nem RAM pré-alocada: total de 7 GiB com ngrok. Configuráveis por DOCKER_*_MEMORY no .env.example. A VM Docker não foi redimensionada. NODE_HEAP_MB=1024 deixa espaço para memória nativa no container Node. Redis usa REDIS_MAXMEMORY=256mb com noeviction, preservando reservas, filas e tokens; ao atingir o teto, rejeita novas escritas em vez de apagar chaves. A diferença até 768 MiB dá margem para overhead e snapshots.

PHP mantém memory_limit=256M por processo e usa quatro workers, com reciclagem a cada 500 requisições; OPcache 192 MiB, até 20.000 arquivos e realpath cache 4 MiB. PCOV está desativado por padrão e restrito a app quando ativado explicitamente com `php -d pcov.enabled=1 artisan test --coverage`. O INI foi montado como zz-custom.ini para prevalecer sobre a configuração antiga da extensão. A imagem também foi corrigida para instalações futuras. Validação de timestamps permanece ativa para desenvolvimento.

As configurações padrão do PostgreSQL (shared_buffers=128MB, work_mem=4MB, max_connections=100) foram inspecionadas e mantidas; não houve evidência de pressão que justificasse alterá-las. PostgreSQL, Redis e Nginx receberam limites em execução via docker update, sem recriação. O teto do Redis também foi aplicado com CONFIG SET. As mesmas configurações ficaram registradas no Compose para futuras criações. PHP, Horizon, Scheduler e Node foram recriados para carregar INI, heap e FPM. Containers reiniciados precisam aquecer caches: não comparar a amostra de RAM logo após reinício com a anterior como economia garantida.
Validação após aplicar os limites: PCOV Disabled, PHP-FPM max_children=4 e max_requests=500, Redis maxmemory=268435456/noeviction e Horizon running. Primeira rodada após reinício retornou HTTP 200 em HTML, sessão, catálogo, JS e CSS. Catálogo: 5.989 ms (bootstrap 3.347 ms, aplicação 2.624 ms, SQL 55 ms); HTML frio 12.155 ms. Esses resultados não mostram eliminação da latência nem permitem atribuir ganho isolado ao desligamento do PCOV; permanecem custos de inicialização e do volume. As leituras Node continuaram mais lentas no volume (614 ms/100 leituras) que no temporário (116 ms).

## Comparação isolada de filesystem

Concluída em 17/10/2026: bootstrap CLI mediano de 10.459 ms no volume Windows contra 407 ms no filesystem Linux do container (25,7 vezes). Mesmo código, 563 arquivos e pico de 22 MiB em todas as execuções. Não é comparação HTTP/FPM nem garante o mesmo ganho no site. Método e três amostras por cenário em [COMPARACAO-FILESYSTEM.md](COMPARACAO-FILESYSTEM.md). Cópias temporárias removidas; projeto original preservado.
