import { useEffect, useRef, useState } from 'react';
import { useLoja } from '../context/LojaContext';

// GET não renova a reserva nem o prazo de abandono do carrinho.
export default function useAtualizacaoCarrinho() {
    const { executarCarrinho, carrinhoOcupado } = useLoja();
    const ocupado = useRef(carrinhoOcupado);
    ocupado.current = carrinhoOcupado;
    const [erro, setErro] = useState(null);

    useEffect(() => {
        let ativo = true;
        let consultando = false;
        const atualizar = async () => {
            if (document.hidden || ocupado.current || consultando) return;
            consultando = true;
            try {
                await executarCarrinho(async () => {});
                if (ativo) setErro(null);
            } catch (falha) {
                if (ativo) setErro(falha);
            } finally { consultando = false; }
        };
        atualizar();
        const intervalo = window.setInterval(atualizar, 30000);
        window.addEventListener('focus', atualizar);
        document.addEventListener('visibilitychange', atualizar);
        return () => {
            ativo = false;
            window.clearInterval(intervalo);
            window.removeEventListener('focus', atualizar);
            document.removeEventListener('visibilitychange', atualizar);
        };
    }, [executarCarrinho]);

    return erro;
}
