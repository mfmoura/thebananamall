import { definirSessao, requisitar, sessao } from './api';

export async function entrar(credenciais) {
    await sessao('/oauth/login', credenciais);
    const dados = await sessao('/bff/authorize', {});
    window.location.assign(dados.authorization_url);
}

export async function concluirOAuth() {
    const estado = await requisitar('/bff/session');
    if (!estado.autenticado) throw new Error('Autorização inválida ou expirada. Entre novamente.');
    definirSessao(true);
}

export async function sair() {
    try { await sessao('/bff/logout', {}); }
    finally { definirSessao(false); }
}