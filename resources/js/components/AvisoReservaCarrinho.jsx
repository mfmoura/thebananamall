import { useEffect, useState } from 'react';
import { useLoja } from '../context/LojaContext';

export default function AvisoReservaCarrinho() {
    const { carrinho } = useLoja();
    const [agora, setAgora] = useState(() => Date.now());
    const prazo = Number(carrinho.reserva_expira_em || 0) * 1000;
    useEffect(() => {
        setAgora(Date.now());
        if (prazo <= Date.now()) return;
        const timer = setTimeout(() => setAgora(Date.now()), Math.min(prazo - Date.now() + 50, 2147483647));
        return () => clearTimeout(timer);
    }, [prazo]);
    if (!carrinho.itens?.length || prazo > agora) return null;

    return <p className="menu-observacao" role="status">A reserva de estoque expirou. Seus itens continuam no carrinho, mas a disponibilidade será conferida novamente ao finalizar a compra.</p>;
}
