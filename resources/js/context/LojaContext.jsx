import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { api, definirSessao, requisitar, sessao } from '../services/api';
import { carrinhoApi } from '../services/carrinho';
import { sair } from '../services/oauth';

const Context = createContext(null);
export const useLoja = () => useContext(Context);

export function LojaProvider({ children }) {
    const [usuario, setUsuario] = useState(null);
    const [carregando, setCarregando] = useState(true);
    const [carrinho, setCarrinho] = useState({ itens: [] });
    const [erro, setErro] = useState('');
    const [carrinhoOcupado, setCarrinhoOcupado] = useState(false);
    const mutacaoCarrinho = useRef(false);
    const atualizarCarrinho = useCallback(async () => {
        const dados = await carrinhoApi.consultar();
        setCarrinho(dados || { itens: [] });
    }, []);
    const atualizarUsuario = useCallback(async () => {
        const dados = await api.me();
        setUsuario(dados);
        return dados;
    }, []);
    const executarCarrinho = useCallback(async (acao) => {
        if (mutacaoCarrinho.current) throw new Error('Aguarde a atualização do carrinho.');
        mutacaoCarrinho.current = true;
        setCarrinhoOcupado(true);
        try {
            await acao();
            await atualizarCarrinho();
        } finally {
            mutacaoCarrinho.current = false;
            setCarrinhoOcupado(false);
        }
    }, [atualizarCarrinho]);
    useEffect(() => {
        const expirar = () => { setUsuario(null); setCarrinho({ itens: [] }); setErro('Sua sessão expirou. Entre novamente para continuar.'); };
        window.addEventListener('lcb:sessao-expirada', expirar);
        return () => window.removeEventListener('lcb:sessao-expirada', expirar);
    }, []);
    useEffect(() => {
        (async () => {
            try {
                const legado = localStorage.getItem('lcb.visitante');
                if (legado) {
                    try {
                        await sessao('/bff/visitante', { token: legado });
                        localStorage.removeItem('lcb.visitante');
                    } catch (e) {
                        if (e.status === 401) localStorage.removeItem('lcb.visitante');
                        else throw e;
                    }
                }
                const estado = await requisitar('/bff/session');
                definirSessao(estado.autenticado);
                if (estado.autenticado) await atualizarUsuario();
                await atualizarCarrinho();
            } catch (e) {
                if (e.status === 401) { definirSessao(false); setUsuario(null); }
                else setErro(e.message);
            } finally { setCarregando(false); }
        })();
    }, [atualizarCarrinho, atualizarUsuario]);
    const logout = async () => {
        try { await sair(); } finally {
            setUsuario(null); setCarrinho({ itens: [] }); setErro('');
        }
    };
    return <Context.Provider value={{ usuario, carregando, carrinho, carrinhoOcupado, executarCarrinho, atualizarCarrinho, atualizarUsuario, logout, erro, setErro }}>{children}</Context.Provider>;
}
