import { useEffect } from 'react';
import { useLoja } from '../context/LojaContext';

export function useMenus(nav) {
    useEffect(() => {
        const fechar = (event) => {
            if (event.type === 'keydown' && event.key !== 'Escape') return;
            nav.current?.querySelectorAll('details[open]').forEach((menu) => {
                if (event.type === 'keydown' || !menu.contains(event.target)) {
                    menu.open = false;
                    if (event.type === 'keydown') menu.querySelector('summary').focus();
                }
            });
        };
        document.addEventListener('click', fechar);
        document.addEventListener('keydown', fechar);
        return () => {
            document.removeEventListener('click', fechar);
            document.removeEventListener('keydown', fechar);
        };
    }, [nav]);

    return (event) => {
        if (!event.currentTarget.open) return;
        nav.current.querySelectorAll('details').forEach((menu) => {
            if (menu !== event.currentTarget) menu.open = false;
        });
    };
}

export function useCarrinhoResumo() {
    const { carrinho } = useLoja();
    const itens = (carrinho.itens || []).map((item) => ({ ...item, precoCentavos: Math.round(Number(item.preco_atual ?? item.preco ?? 0) * 100) }));
    return {
        itens,
        quantidade: itens.reduce((soma, item) => soma + item.quantidade, 0),
        total: itens.reduce((soma, item) => soma + Math.round(item.quantidade * item.precoCentavos), 0),
    };
}
