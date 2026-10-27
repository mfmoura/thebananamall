# Tarefas — painel de customização da identidade

Planejamento em 27/10/2026. **Nenhuma das funções abaixo está implementada.** Ponto de partida: [identidade atual](IDENTIDADE-VISUAL.md). Integrar ao dashboard React existente e à API Laravel, sem criar outro sistema de administração.

## 1. Escopo e autorização

- [ ] Definir a primeira entrega: nome público, logos horizontal/quadrado e versão branca, favicon, cores primária/destaque/fundo/texto. Manter fontes de sistema inicialmente.
- [ ] Usar a página administrativa `manutencao` como permissão proposta; confirmar a regra antes da implementação. Validar no backend perfil ativo, permissão e escopo administrativo. O menu React somente reflete essa autorização.
- [ ] Definir uma identidade por instalação. Não introduzir multiempresa ou multitenancy sem requisito específico.
- [ ] Definir quais campos públicos são expostos e quais dados de auditoria ficam restritos aos administradores.

## 2. Persistência e publicação

- [ ] Criar configuração persistente de identidade em PostgreSQL, com campos explícitos, casts e `SoftDeletes`/`deleted_at`. Não editar `.env` pelo painel.
- [ ] Definir precedência: identidade publicada no banco, seguida dos padrões atuais de configuração/arquivos quando ausente. Separar nome visual de `APP_NAME` e dados fiscais.
- [ ] Implementar service para leitura, validação de negócio, gravação e publicação; controllers finos e FormRequests distintos para operações com regras diferentes.
- [ ] Salvar a identidade como conjunto consistente em transação, sem exibir metade de uma atualização. Controlar edição concorrente por versão e informar conflitos.
- [ ] Separar prévia local de publicação: editar o formulário não altera a loja até salvar/publicar com sucesso.
- [ ] Registrar autor, data e valores anteriores de cada publicação; permitir restaurar uma versão anterior ou os padrões sem apagar histórico nem arquivos referenciados.

## 3. API e integração administrativa

- [ ] Definir contratos para consulta pública da identidade publicada, consulta administrativa, upload, publicação e restauração. Preservar o formato de resposta existente.
- [ ] Adicionar rotas em `routes/api.php` e permissões de escrita apropriadas. Limitar a consulta pública aos dados necessários para renderização, sem caminhos internos ou segredos.
- [ ] Adicionar apenas as rotas necessárias à lista explícita do proxy administrativo; preservar sessão HttpOnly, CSRF, OAuth/PKCE e middlewares originais. Não retornar tokens ao navegador.
- [ ] Consumir os contratos pelos serviços HTTP existentes; usar a mesma identidade no Blade e no React para evitar divergência de título, favicon e primeiro carregamento.

## 4. Arquivos de marca

- [ ] Definir formatos e limites por finalidade: PNG/WebP para logos; ICO ou conversão de PNG validado para favicon. Validar conteúdo/MIME real, tamanho e dimensões; rejeitar SVG nesta primeira entrega, salvo sanitização específica.
- [ ] Gerar nomes de arquivo no servidor, impedir caminhos enviados pelo cliente e servir arquivos com tipos corretos. Não aceitar HTML, scripts ou URLs remotas arbitrárias como logo.
- [ ] Preservar transparência e proporção; apresentar orientação para logo quadrado, horizontal e branco. Usar fallback legível quando uma variante não existir.
- [ ] Gerar favicon com 16, 32, 48, 64, 128 e 256 px, usando processamento local da imagem enviada. Não exigir geração por IA no painel.
- [ ] Usar URLs versionadas para evitar cache antigo, incluindo favicon. Publicar novas referências somente após os arquivos estarem disponíveis.
- [ ] Preservar assets associados a versões publicadas para restauração e auditoria. Tratar uploads temporários separadamente, com política explícita para arquivos que nunca foram publicados.

## 5. Tema e componentes React

- [ ] Mapear cores fixas remanescentes e distinguir cores de marca das cores semânticas de erro, sucesso e aviso.
- [ ] Centralizar tokens de tema e aplicar somente valores de cor validados; não permitir CSS, HTML ou JavaScript livre.
- [ ] Validar contraste dos pares texto/fundo, botão/texto e links/fundo, incluindo foco e estados desabilitados. Explicar os ajustes necessários quando uma combinação for inválida.
- [ ] Criar a página de identidade no dashboard com componentes reutilizáveis, campos de cor e valor hexadecimal, uploads, validações por campo e indicação de alterações não salvas.
- [ ] Exibir prévia em fundos claro e escuro, cabeçalho, rodapé e botão, com alternância desktop/celular. Usar a versão branca quando o fundo exigir.
- [ ] Atualizar loja e dashboard a partir da identidade publicada; manter alternativas textuais, título da aba e favicon coerentes. Evitar flashes do tema padrão.
- [ ] Informar progresso, sucesso, conflito e erro sem perder o formulário. Solicitar confirmação somente para descartar edição ou restaurar padrões.

## 6. Cache, testes e documentação

- [ ] Se houver cache, invalidar após commit e incluir versão nas referências. Falhas de leitura devem manter a última identidade válida ou os padrões.
- [ ] Adicionar testes feature para leitura pública filtrada, escrita autorizada, 401/403, perfil excluído, CSRF/proxy e validações de nome/cor/upload.
- [ ] Testar publicação consistente, conflito de versão, restauração, preservação de histórico e fallback sem configuração no banco.
- [ ] Cobrir no frontend prévia sem publicação, erros por campo, falha ao salvar e descarte de edição; conferir manualmente responsividade, teclado, contraste e favicon.
- [ ] Atualizar a coleção Postman, seu README e OpenAPI com contratos reais, autenticação, payloads, uploads e respostas. Atualizar os guias de identidade, dashboard e deploy.
- [ ] Executar testes pertinentes, build e revisão visual. Registrar resultados e limitações antes de concluir a entrega.

## Critérios de aceite

- Um administrador com a permissão definida altera a identidade pelo dashboard sem editar arquivos nem reconstruir o frontend.
- Visitantes, Clientes e administradores sem permissão não conseguem gravar identidade, mesmo chamando a API diretamente.
- Nome, logos, título, favicon e cores publicados permanecem consistentes após recarga e entre loja e painel.
- Prévia não afeta visitantes; falhas de validação ou upload não substituem a versão publicada.
- É possível restaurar a identidade The Banana Mall ou uma versão anterior sem excluir dados de domínio e histórico.
- Fluxos de autenticação, carrinho, preços e pagamentos mantêm seu comportamento.
