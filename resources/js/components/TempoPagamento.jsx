import { useEffect, useState } from 'react';
import { formatarTempoPagamento, tempoPagamento } from '../services/tempoPagamento';

export default function TempoPagamento({ pagamento }) {
    const [agora, setAgora] = useState(Date.now);
    useEffect(() => {
        const atualizar = () => setAgora(Date.now());
        const intervalo = setInterval(atualizar, 1000);
        document.addEventListener('visibilitychange', atualizar);
        return () => { clearInterval(intervalo); document.removeEventListener('visibilitychange', atualizar); };
    }, []);
    const tempo = tempoPagamento(pagamento, agora);
    if (tempo.segundos === null) return null;
    const esgotado = tempo.restante && tempo.segundos === 0;
    return <div className={`pagamento-tempo${esgotado ? ' pagamento-tempo-esgotado' : ''}`}>
        <div className="pagamento-tempo-cabecalho">
            <span><i className="fa-regular fa-clock" aria-hidden="true" /> {tempo.restante ? 'Tempo restante para pagar' : 'Aguardando confirmação há'}</span>
            <span className="pagamento-tempo-relogio" role="timer" aria-live="off" aria-label={tempo.restante ? 'Tempo restante para pagar' : 'Tempo de espera'}>{formatarTempoPagamento(tempo.segundos)}</span>
        </div>
        {tempo.restante && <progress className="pagamento-tempo-barra" max="100" value={tempo.percentual} aria-label="Prazo de pagamento restante" />}
        {esgotado ? <p role="status">O prazo informado terminou. Aguarde a atualização do pedido antes de tentar outro pagamento. Se já pagou, aguarde a confirmação.</p>
            : <p>{tempo.restante ? 'Pague dentro do prazo e acompanhe a confirmação neste pedido.' : 'O provedor ainda não informou um prazo. A confirmação será atualizada automaticamente.'}</p>}
    </div>;
}
