import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { api, definirSessao, requisitar, sessao } from '../services/api';
import { carrinhoApi } from '../services/carrinho';
import { entrar, concluirOAuth, sair } from '../services/oauth';

const Context = createContext(null);
export const useLoja = () => useContext(Context);

export function LojaProvider({ children }) {
    const [usuario, setUsuario] = useState(null);
    const [carregando, setCarregando] = useState(true);
    const [carrinho, setCarrinho] = useState({ itens: [] });
    const [erro, setErro] = useState('');
    const [carrinhoOcupado, setCarrinhoOcupado] = useState(false);
    const mutacaoCarrinho = useRef(false);
    const inicializacaoIniciada = useRef(false);
    const inicializacao = useRef(Promise.resolve());
    const autenticacaoEmCurso = useRef(false);
    const [processandoAutenticacao, setProcessandoAutenticacao] = useState(false);
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
        // A negociação da sessão ocorre uma vez por provider, inclusive no StrictMode.
        if (inicializacaoIniciada.current) return;
        inicializacaoIniciada.current = true;
        inicializacao.current = (async () => {
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
    const finalizarLogin = useCallback(async () => {
        await inicializacao.current;
        await concluirOAuth();
        const conta = await atualizarUsuario();
        if (conta.cliente) {
            try { await carrinhoApi.mesclar(); }
            catch { setErro('Não foi possível recuperar os itens do carrinho anterior.'); }
        }
        await atualizarCarrinho();
    }, [atualizarUsuario, atualizarCarrinho]);
    const login = async (credenciais) => {
        if (autenticacaoEmCurso.current) return;
        autenticacaoEmCurso.current = true;
        setProcessandoAutenticacao(true);
        setErro('');
        try {
            await inicializacao.current;
            await entrar(credenciais);
            await finalizarLogin();
        } finally {
            autenticacaoEmCurso.current = false;
            setProcessandoAutenticacao(false);
        }
    };
    const logout = async () => {
        if (autenticacaoEmCurso.current) return;
        autenticacaoEmCurso.current = true;
        setProcessandoAutenticacao(true);
        try {
            await inicializacao.current;
            await sair();
            setUsuario(null); setCarrinho({ itens: [] }); setErro('');
        } finally {
            autenticacaoEmCurso.current = false;
            setProcessandoAutenticacao(false);
        }
    };
    return <Context.Provider value={{ usuario, carregando, carrinho, carrinhoOcupado, executarCarrinho, atualizarCarrinho, atualizarUsuario, login, finalizarLogin, logout, processandoAutenticacao, erro, setErro }}>{children}</Context.Provider>;
}
