import { requisitar } from './api';

const chamar = (path = '', method = 'GET', body) => requisitar(`/api/carrinho${path}`, { method, body });

export const carrinhoApi = {
    async consultar() {
        try { return await chamar(); } catch (erro) {
            if (erro.status === 404) return { itens: [] };
            throw erro;
        }
    },
    async adicionar(codigo, quantidade = 1) {
        await chamar('', 'POST');
        return chamar('/itens', 'POST', { codigo, quantidade });
    },
    alterar: (codigo, quantidade) => chamar(`/itens/${codigo}`, 'PATCH', { quantidade }),
    excluir: (codigo) => chamar(`/itens/${codigo}`, 'DELETE'),
    limpar: () => chamar('', 'DELETE'),
    mesclar: () => chamar('/mesclar', 'POST', {}),
};