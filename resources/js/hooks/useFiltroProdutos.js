import { useCallback, useEffect, useState } from 'react';
import { api } from '../services/api';
import usePaginacao from './usePaginacao';

export default function useFiltroProdutos() {
    const [categoria, setCategoria] = useState('');
    const [busca, setBusca] = useState('');
    const [buscaAplicada, setBuscaAplicada] = useState('');
    const [porPagina, setPorPagina] = useState(() => window.matchMedia('(max-width: 767px)').matches ? 4 : 8);

    useEffect(() => {
        const mobile = window.matchMedia('(max-width: 767px)');
        const atualizar = () => setPorPagina(mobile.matches ? 4 : 8);
        atualizar();
        mobile.addEventListener('change', atualizar);
        return () => mobile.removeEventListener('change', atualizar);
    }, []);

    useEffect(() => {
        const temporizador = setTimeout(() => setBuscaAplicada(busca.trim()), 1000);
        return () => clearTimeout(temporizador);
    }, [busca]);

    const buscarPagina = useCallback((pagina, signal) => api.produtos(pagina, signal, {
        categoria, busca: buscaAplicada, porPagina,
    }), [categoria, buscaAplicada, porPagina]);
    const paginacao = usePaginacao(buscarPagina);

    return { ...paginacao, categoria, setCategoria, busca, setBusca, porPagina };
}
