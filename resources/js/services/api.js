// Apenas um indicador de interface; toda autorização pertence ao servidor.
let autenticado = false;
export const definirSessao = (valor) => { autenticado = Boolean(valor); };
export const temSessao = () => autenticado;
sessionStorage.removeItem('lcb.access_token');
sessionStorage.removeItem('lcb.pkce');

export class ApiError extends Error {
    constructor(status, data) {
        super(data?.message || 'Não foi possível concluir a operação.');
        this.status = status;
        this.data = data;
    }
}

export async function requisitar(path, opcoes = {}) {
    try { return await executarRequisicao(path, opcoes); }
    catch (erro) {
        if (erro.name === 'AbortError') throw erro;
        if (erro instanceof TypeError) throw new ApiError(0, { message: 'Não foi possível conectar ao servidor. Confira sua conexão e tente novamente.' });
        if (erro instanceof SyntaxError) throw new ApiError(502, { message: 'O servidor enviou uma resposta inválida. Tente novamente em instantes.' });
        throw erro;
    }
}

async function executarRequisicao(path, { method = 'GET', body, headers = {}, blob = false, signal, cache, invalidarSessao = true } = {}) {
    const destino = path.startsWith('/api/') ? `/bff${path}` : path;
    const escrita = !['GET', 'HEAD'].includes(method);
    const multipart = body instanceof FormData;
    let csrf = {};
    if (escrita) {
        const resposta = await fetch('/oauth/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
        if (!resposta.ok) throw new ApiError(resposta.status, { message: 'Não foi possível iniciar a sessão.' });
        csrf = { 'X-CSRF-TOKEN': (await resposta.json()).csrf_token };
    }
    const response = await fetch(destino, {
        method, credentials: 'same-origin', signal, ...(cache ? { cache } : {}),
        headers: { Accept: 'application/json', ...(body && !multipart ? { 'Content-Type': 'application/json' } : {}), ...headers, ...csrf },
        ...(body ? { body: multipart ? body : JSON.stringify(body) } : {}),
    });
    if (blob && response.ok) return response.blob();
    const json = await response.json().catch(() => null);
        const data = json && 'statusCode' in json ? json.data : json;
    if (!response.ok) {
        if (response.status === 401 && invalidarSessao && autenticado && path.startsWith('/api/')) {
            definirSessao(false);
            window.dispatchEvent(new Event('lcb:sessao-expirada'));
        }
        throw new ApiError(response.status, data);
    }
    return data;
}

export async function sessao(path, body) {
    return requisitar(path, { method: 'POST', body });
}

export const api = {
    configuracaoPagamento: () => requisitar('/api/pagamentos/configuracao', { cache: 'no-store' }),
    criarPagamento: (id, dados) => requisitar(`/api/pedidos/${id}/pagamento`, { method: 'POST', body: dados }),
    consultarPagamento: (id) => requisitar(`/api/pedidos/${id}/pagamento`),
    categorias: () => requisitar('/api/categorias'),
    produtos: (pagina = 1, signal, filtros = {}) => {
        const parametros = new URLSearchParams({ per_page: filtros.porPagina ?? 8, page: pagina });
        for (const campo of ['categoria', 'busca']) {
            if (filtros[campo]?.trim()) parametros.set(campo, filtros[campo].trim());
        }
        return requisitar(`/api/produtos?${parametros}`, { signal });
    },
    produto: (id) => requisitar(`/api/produtos/${encodeURIComponent(id)}`),
    precos: (id) => requisitar(`/api/produtos/${encodeURIComponent(id)}/precos`),
    estoque: (id) => requisitar(`/api/produtos/${encodeURIComponent(id)}/estoque`),
    me: () => requisitar('/api/me'),
    cadastrar: (body) => requisitar('/api/clientes', { method: 'POST', body }),
    atualizarUsuario: (id, body) => requisitar(`/api/usuarios/${id}`, { method: 'PUT', body }),
    trocarEmail: (id, email) => requisitar(`/api/usuarios/${id}/email`, { method: 'POST', body: { email } }),
    excluirUsuario: (id) => requisitar(`/api/usuarios/${id}`, { method: 'DELETE' }),
    excluirCliente: (id) => requisitar(`/api/clientes/${id}`, { method: 'DELETE' }),
    consultarCep: (cep, signal) => requisitar(`/api/enderecos/cep/${encodeURIComponent(cep)}`, { signal }),
    endereco: (body, id) => requisitar(`/api/enderecos${id ? `/${id}` : ''}`, { method: id ? 'PUT' : 'POST', body }),
    excluirEndereco: (id) => requisitar(`/api/enderecos/${id}`, { method: 'DELETE' }),
    confirmarEmail: (body) => requisitar('/api/email/confirmar', { method: 'POST', body }),
    reenviarEmail: (body) => requisitar('/api/email/reenviar-confirmacao', { method: 'POST', body }),
    recuperarSenha: (body) => requisitar('/api/senha/recuperar', { method: 'POST', body }),
    redefinirSenha: (body) => requisitar('/api/senha/redefinir', { method: 'POST', body }),
    formasPagamento: () => requisitar('/api/formas-pagamento'),
    cotarFrete: (cep) => requisitar('/api/pedidos/frete', { method: 'POST', body: { cep } }),
    pedidos: () => requisitar('/api/pedidos'),
    pedido: (id) => requisitar(`/api/pedidos/${id}`),
    comprar: (body, chave) => requisitar('/api/pedidos', { method: 'POST', body, headers: { 'Idempotency-Key': chave } }),
    cancelarPedido: (id) => requisitar(`/api/pedidos/${id}/cancelar`, { method: 'POST' }),
    danfe: (id) => requisitar(`/api/pedidos/${id}/danfe`, { blob: true }),
};
