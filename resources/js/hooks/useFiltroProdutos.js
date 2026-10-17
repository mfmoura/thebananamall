import { useCallback, useEffect, useState } from 'react';
import { api } from '../services/api';
import usePaginacao from './usePaginacao';

export default function useFiltroProdutos() {
    const [categoria, setCategoria] = useState('');
    const [busca, setBusca] = useState('');
    const [buscaAplicada, setBuscaAplicada] = useState('');

    useEffect(() => {
        const temporizador = setTimeout(() => setBuscaAplicada(busca.trim()), 1000);
        return () => clearTimeout(temporizador);
    }, [busca]);

    const buscarPagina = useCallback((pagina, signal) => api.produtos(pagina, signal, {
        categoria, busca: buscaAplicada,
    }), [categoria, buscaAplicada]);
    const paginacao = usePaginacao(buscarPagina);

    return { ...paginacao, categoria, setCategoria, busca, setBusca };
}
