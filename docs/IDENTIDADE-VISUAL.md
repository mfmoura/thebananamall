# Identidade visual — The Banana Mall

Atualizado em 27/10/2026. Este documento descreve a implementação atual; o [painel de customização](PAINEL-IDENTIDADE-TAREFAS.md) ainda é planejado.

## Nome e configuração

A marca padrão é **The Banana Mall**, com um cacho de bananas em estilo clipart. A aplicação mantém uma base white-label, mas a troca completa da identidade ainda requer alterações em arquivos.

- `config/marca.php` lê `MARCA_NOME`, com fallback para The Banana Mall.
- `resources/views/layouts/loja.blade.php` fornece o nome no título e na meta tag `marca-nome`.
- `resources/js/marca.js` lê essa meta tag e fornece o nome ao React, incluindo textos e alternativas das imagens.
- `APP_NAME` é independente de `MARCA_NOME`; revise os dois e `MAIL_FROM_NAME` ao preparar um ambiente. O nome desenhado dentro do logo não muda ao alterar essas variáveis.

Após alterar o ambiente, execute `docker compose exec php php artisan config:clear`. Reaplique o cache de configuração conforme o procedimento de publicação. CSS e React precisam de novo build: `docker compose run --rm --no-deps node npm run build`.

## Paleta e tipografia

As variáveis ficam em `resources/css/app.css` e são compartilhadas pela loja e pelo dashboard.

| Variável | Valor padrão | Uso |
| --- | --- | --- |
| `--marca-primaria` | `#979700` | Superfícies de marca, botões e bordas |
| `--marca-destaque` | `#313100` | Links, ícones e destaques sobre branco |
| `--marca-fundo` | `#ffffff` | Fundo base |
| `--marca-texto` | `#000000` | Texto padrão |

A tipografia usa fontes de sistema, sem a antiga Breda Two. Ainda existem cores locais para estados, bordas e componentes; as quatro variáveis não representam todos os estilos. O [backlog do painel](PAINEL-IDENTIDADE-TAREFAS.md) inclui consolidar esses usos antes de liberar paletas arbitrárias.

## Logos e favicon

Todos os PNGs têm fundo transparente. A variante branca é destinada a fundos escuros e não é selecionada automaticamente pelo tema atual.

| Arquivo relativo à raiz | Finalidade atual |
| --- | --- |
| `public/images/the-banana-mall-logo.png` | Logo horizontal usado em `Rodape.jsx` |
| `public/images/the-banana-mall-square.png` | Logo 1:1 usado em `BarraSuperior.jsx` |
| `public/images/the-banana-mall-square-white.png` | Logo 1:1 com letras brancas, disponível para fundos escuros |
| `public/images/the-banana-mall-icon.png` | Cacho sem texto, fonte do favicon |
| `public/favicon.ico` | Ícone da aba, referenciado no layout Blade; contém 16, 32, 48, 64, 128 e 256 px |

Os logos foram produzidos pela ferramenta de geração de imagens: cacho de bananas estilo clipart, amarelo e oliva, texto exato “The Banana Mall”, fundo transparente; variantes quadrada e com letras brancas. O ICO foi convertido a partir do ícone sem texto.

## Substituição manual e conferência

1. Defina o nome no ambiente e revise os nomes de aplicação e remetente.
2. Substitua os arquivos de logo ou ajuste os caminhos em `BarraSuperior.jsx` e `Rodape.jsx`. Preserve proporção, transparência e texto alternativo coerente com a nova marca.
3. Atualize a paleta no CSS e o favicon no diretório público. Recompile se houver alterações em CSS/React.
4. Confira cabeçalho, rodapé, catálogo, formulários, checkout e dashboard em desktop e celular, incluindo foco por teclado e contraste. Confira o favicon em nova aba, considerando o cache do navegador.

Alterar a identidade visual não altera a razão social, documentos fiscais ou cadastros em provedores externos. Esses dados devem ser revisados separadamente pelo responsável.

## Compatibilidade e histórico

Logos e links institucionais da LCB foram removidos da apresentação. Os seeders e mockups de demonstração foram neutralizados; produtos já persistidos não foram renomeados automaticamente. Revise-os pelo fluxo administrativo, preservando histórico e registros excluídos logicamente.

O arquivo `postman/LojaVirtualLCB.postman_collection.json`, SKUs históricos e identificadores internos legados continuam existindo por compatibilidade. Nomes de cookies, chaves de armazenamento e políticas de segurança não devem ser renomeados apenas por estética. Relatórios antigos de homologação preservam a marca e as cores usadas na época.
