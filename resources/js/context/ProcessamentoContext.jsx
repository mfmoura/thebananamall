import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import BloqueioProcessamento from '../components/BloqueioProcessamento';

const Context = createContext(null);
export const useProcessamento = () => useContext(Context);

export function ProcessamentoProvider({ children }) {
    const [operacoes, setOperacoes] = useState(() => new Map());
    const iniciar = useCallback((rotulo = 'Processando, aguarde') => {
        const id = Symbol();
        setOperacoes((atuais) => new Map(atuais).set(id, rotulo));
        return () => setOperacoes((atuais) => {
            const proximas = new Map(atuais);
            proximas.delete(id);
            return proximas;
        });
    }, []);
    const executar = useCallback(async (acao, rotulo) => {
        const concluir = iniciar(rotulo);
        try { return await acao(); }
        finally { concluir(); }
    }, [iniciar]);
    const value = useMemo(() => ({ iniciar, executar }), [iniciar, executar]);
    return <Context.Provider value={value}>
        {children}
        {operacoes.size > 0 && <BloqueioProcessamento rotulo={[...operacoes.values()].at(-1)} />}
    </Context.Provider>;
}
