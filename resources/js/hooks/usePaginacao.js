import { useCallback, useEffect, useRef, useState } from 'react';

export default function usePaginacao(buscarPagina) {
    const [itens, setItens] = useState([]);
    const [carregando, setCarregando] = useState(true);
    const [erro, setErro] = useState(null);
    const [temMais, setTemMais] = useState(true);
    const pagina = useRef(1);
    const requisicao = useRef(null);
    const finalizado = useRef(false);

    const carregarMais = useCallback(async () => {
        if (requisicao.current || finalizado.current) return;
        const controller = new AbortController();
        requisicao.current = controller;
        setCarregando(true); setErro(null);
        try {
            const resposta = await buscarPagina(pagina.current, controller.signal);
            if (requisicao.current !== controller) return;
            setItens((anteriores) => [...new Map([...anteriores, ...resposta.data].map((item) => [item.id, item])).values()]);
            finalizado.current = !resposta.next_page_url;
            setTemMais(!finalizado.current);
            pagina.current = resposta.current_page + 1;
        } catch (e) {
            if (requisicao.current === controller && e.name !== 'AbortError') setErro(e);
        } finally {
            if (requisicao.current === controller) { requisicao.current = null; setCarregando(false); }
        }
    }, [buscarPagina]);

    useEffect(() => {
        pagina.current = 1; finalizado.current = false;
        setItens([]); setTemMais(true);
        carregarMais();
        return () => { requisicao.current?.abort(); requisicao.current = null; };
    }, [carregarMais]);

    return { itens, carregando, erro, temMais, carregarMais };
}
