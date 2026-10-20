import { requisitar, sessao } from './api';

export async function prepararDashboard() {
    const usuario = await requisitar('/dashboard/session');
    if (!usuario.api_autorizada) {
        const { authorization_url: destino } = await sessao('/dashboard/authorize', {});
        const url = new URL(destino, window.location.origin);
        if (url.origin !== window.location.origin || url.pathname !== '/dashboard/oauth/authorize') throw new Error('Autorização administrativa inválida.');
        const resposta = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
        const retorno = new URL(resposta.url);
        if (!resposta.ok || retorno.origin !== window.location.origin || retorno.pathname !== '/dashboard/') throw new Error('Não foi possível autorizar o painel. Tente entrar novamente.');
    }
    return usuario;
}

async function consultar(path, opcoes) {
    try { return await requisitar(`/dashboard/api/${path}`, opcoes); }
    catch (erro) {
        if (erro.status === 401) window.dispatchEvent(new Event('lcb:dashboard-expirado'));
        throw erro;
    }
}

export const dashboardApi = {
    produtosLogistica: (parametros) => consultar(`admin/logistica/produtos?${parametros}`),
    produtoLogistica: (id) => consultar(`admin/logistica/produtos/${id}`),
    historicoEstoque: (parametros) => consultar(`admin/logistica/movimentacoes?${parametros}`),
    movimentarEstoque: (body) => consultar('admin/estoque/movimentacoes', { method: 'POST', body }),
    pedidos: (parametros) => consultar(`admin/pedidos?${parametros}`),
    pedido: (id) => consultar(`admin/pedidos/${id}`),
    cancelarPedido: (id) => consultar(`admin/pedidos/${id}/cancelar`, { method: 'POST' }),
    danfePedido: (id) => consultar(`admin/pedidos/${id}/danfe`, { blob: true }),
    etiquetaPedido: (id) => consultar(`admin/pedidos/${id}/etiqueta`),
    usuarios: (parametros) => consultar(`admin/usuarios?${parametros}`),
    usuario: (id) => consultar(`admin/usuarios/${id}`),
    criarUsuario: (body) => consultar('admin/usuarios', { method: 'POST', body }),
    atualizarUsuario: (id, body) => consultar(`admin/usuarios/${id}`, { method: 'PATCH', body }),
    excluirUsuario: (id) => consultar(`admin/usuarios/${id}`, { method: 'DELETE' }),
    trocarEmailUsuario: (id, body) => consultar(`admin/usuarios/${id}/email`, { method: 'POST', body }),
    listar: (parametros) => consultar(`admin/produtos?${parametros}`),
    produto: (id) => consultar(`admin/produtos/${id}`),
    categorias: () => consultar('categorias'),
    criar: (body) => consultar('admin/produtos', { method: 'POST', body }),
    atualizar: (id, body) => consultar(`admin/produtos/${id}`, { method: 'PATCH', body }),
    excluir: (id) => consultar(`admin/produtos/${id}`, { method: 'DELETE' }),
    preco: (id, body) => consultar(`admin/produtos/${id}/precos`, { method: 'POST', body }),
    adicionarFoto: (id, body) => consultar(`admin/produtos/${id}/imagens`, { method: 'POST', body }),
    enviarFoto: (id, arquivo, principal = false) => {
        const body = new FormData();
        body.append('arquivo', arquivo);
        body.append('principal', principal ? '1' : '0');
        return consultar(`admin/produtos/${id}/imagens/upload`, { method: 'POST', body });
    },
    atualizarFoto: (id, imagem, body) => consultar(`admin/produtos/${id}/imagens/${imagem}`, { method: 'PATCH', body }),
    excluirFoto: (id, imagem) => consultar(`admin/produtos/${id}/imagens/${imagem}`, { method: 'DELETE' }),
};
