import { definirSessao, requisitar, sessao } from './api';

export async function entrar(credenciais) {
    await sessao('/oauth/login', credenciais);
    const dados = await sessao('/bff/authorize', {});
    const url = new URL(dados.authorization_url, window.location.origin);
    if (url.origin !== window.location.origin || url.pathname !== '/oauth/authorize') {
        throw new Error('Não foi possível iniciar a autorização.');
    }
    const resposta = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    const destino = new URL(resposta.url);
    if (!resposta.ok || destino.origin !== window.location.origin || destino.pathname !== '/oauth/callback') {
        throw new Error('Não foi possível concluir o acesso. Tente novamente.');
    }
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
