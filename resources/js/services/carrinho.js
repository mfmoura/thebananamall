import { requisitar } from './api';

const chamar = (path = '', method = 'GET', body) => requisitar(`/api/carrinho${path}`, { method, body });

export const carrinhoApi = {
    async consultar() {
        try { return await chamar(); } catch (erro) {
            if (erro.status === 404) return { itens: [] };
            throw erro;
        }
    },
    async adicionar(codigo, quantidade = 1, tamanho = null) {
        await chamar('', 'POST');
        return chamar('/itens', 'POST', { codigo, quantidade, tamanho });
    },
    alterar: (codigo, quantidade, tamanho = null) => chamar(`/itens/${codigo}`, 'PATCH', { quantidade, tamanho }),
    excluir: (codigo, tamanho = null) => chamar(`/itens/${codigo}${tamanho === null ? '' : `?${new URLSearchParams({ tamanho })}`}`, 'DELETE'),
    limpar: () => chamar('', 'DELETE'),
    mesclar: () => chamar('/mesclar', 'POST', {}),
};