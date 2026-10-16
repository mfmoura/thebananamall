# Instruções de desenvolvimento

Este arquivo registra as convenções do projeto para qualquer pessoa ou LLM que contribua com o código. Leia-o antes de implementar mudanças.

## Contexto

- O projeto é uma API Laravel. Não criar páginas ou fluxos de interface web sem solicitação explícita.
- O modelo de usuário da aplicação é `App\Models\Usuario`, armazenado atualmente na tabela `users`.
- O projeto usa PostgreSQL e Redis via Docker Compose. Não altere a infraestrutura ou suas credenciais sem necessidade para a tarefa.
- O projeto busca seguir boas práticas e os princípios SOLID; prefira mudanças pequenas, coesas e fáceis de manter.

## Organização e responsabilidades

- **Controllers (`app/Http/Controllers/Api`)**: devem coordenar a requisição e a resposta HTTP. Mantenha-os finos: não coloque neles regras de negócio, validação inline ou operações de persistência.
- **Services (`app/Services`)**: concentram operações de negócio e processamento reutilizável. Controllers devem delegar essas operações aos services, em vez de duplicar lógica.
- **FormRequests (`app/Http/Requests/Api`)**: encapsulam validação e autorização específicas da requisição. Use classes distintas quando as regras ou permissões forem diferentes; controllers devem consumir `$request->validated()`.
- **Models (`app/Models`)**: representam os dados e relacionamentos do Eloquent. Mantenha campos graváveis, dados ocultos e casts explícitos e coerentes.
- **Rotas (`routes/api.php`)**: mantenha os endpoints da API nesse arquivo e aplique autenticação e middleware na rota ou no grupo apropriado.
- **Postman (`postman/LojaVirtualLCB.postman_collection.json`)**: sempre que uma mudança adicionar ou alterar uma função acessível pela API, atualize a coleção com a requisição, autenticação, payload e variáveis necessárias. Mantenha as instruções em `postman/README.md` coerentes com os fluxos disponíveis.

## Convenções

- Siga os padrões e versões já presentes no projeto antes de introduzir dependências ou arquiteturas novas.
- Use nomes em português para conceitos de domínio já nomeados em português, como `Usuario`; preserve os nomes convencionais do Laravel quando fizerem parte de APIs ou ferramentas do framework.
- Injete dependências pelo container do Laravel. Evite acoplamento desnecessário entre controller, banco de dados e regras de negócio.
- Respostas HTTP pertencem à camada de apresentação. Não invente um envelope global ou altere o formato existente sem solicitação; o formato padrão de resposta da API ainda é uma decisão que o responsável pelo projeto quer controlar.
- Senhas devem ser armazenadas usando o hash seguro do Laravel (cast `hashed` ou `Hash`). **Base64 não é hash e não deve ser usado para proteger senhas.** Nunca retorne senhas ou tokens secretos em respostas.
- Preserve autorização por usuário nas operações protegidas. Não remova verificações de propriedade ao refatorar endpoints.
- Evite abstrações genéricas ou camadas adicionais sem uma necessidade concreta demonstrada pelo código.

## Testes e alterações

- Escreva testes PHPUnit de feature para comportamentos de endpoints e testes unitários para lógica isolada quando isso trouxer cobertura útil.
- Ao alterar um comportamento existente, atualize ou acrescente testes que cubram o caminho esperado e falhas relevantes.
- Execute os testes pertinentes quando solicitado e reporte claramente o resultado ou qualquer impedimento do ambiente.
- Não reverta mudanças locais preexistentes nem modifique arquivos fora do escopo necessário.
