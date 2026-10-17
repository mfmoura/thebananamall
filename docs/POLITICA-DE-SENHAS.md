# Política de senhas

- Senhas novas devem ter de 8 a 15 caracteres, conforme a regra definida para o projeto. Essa faixa difere da recomendação NIST descrita abaixo. O sistema não exige combinações artificiais de maiúsculas, números ou símbolos. Senhas existentes continuam válidas para login; o intervalo se aplica ao cadastro e às trocas de senha.
- O sistema rejeita a senha atual e as quatro senhas anteriores.
- A senha e o histórico são armazenados com o hasher adaptativo configurado no Laravel. Um HMAC-SHA-256 com `PASSWORD_PEPPER` é aplicado antes do hash adaptativo. SHA-256 sozinho não é usado para armazenar senhas.
- `PASSWORD_PEPPER` deve ser um segredo aleatório, distinto de `APP_KEY`, definido no ambiente de instalação e protegido como segredo. Se omitido, a configuração usa `APP_KEY` como fallback por compatibilidade; prefira preencher `PASSWORD_PEPPER` e não o altere sem um plano de migração de credenciais.
- Senhas não expiram periodicamente. A troca é exigida em recuperação, primeiro acesso e após suspeita ou evidência de comprometimento.
- Uma tentativa de login com qualquer uma das quatro senhas anteriores é recusada e gera e-mail de alerta sem incluir a senha.
- As tentativas de login têm limitação de frequência. Tokens de acesso são revogados nos fluxos de redefinição de senha existentes.

## Referências e escopo

A LGPD não define tamanho mínimo, histórico ou periodicidade de troca para senhas. Ela exige medidas técnicas e administrativas de segurança compatíveis com o risco, especialmente no art. 46. Manter somente hashes adaptativos e limitar o histórico atende à minimização, mas não substitui controles organizacionais, gestão de incidentes, controle de acesso, retenção e atendimento aos direitos dos titulares.

A ISO/IEC 27001:2022 também não prescreve um tamanho ou ciclo universal de troca. O controle A.5.17 trata da gestão das informações de autenticação e da orientação aos usuários; a seleção e evidência dos controles dependem da avaliação de riscos e do escopo do sistema de gestão da organização.

O NIST SP 800-63B-4 orienta minimo de 15 caracteres quando a senha e o unico fator, aceitar ao menos 64 caracteres, nao impor regras de composicao e nao exigir troca periodica sem evidencia de comprometimento. Por decisão do projeto, não há consulta externa a bases de senhas vazadas. A verificacao contextual rejeita correspondencias com login, e-mail e nome da loja, incluindo caixa diferente, separadores removidos, substituicoes comuns por numeros e sufixos numericos previsiveis.
