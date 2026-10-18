import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

export default function BloqueioProcessamento({ rotulo = 'Processando, aguarde' }) {
    const ref = useRef(null);
    useEffect(() => {
        const anterior = document.activeElement;
        const overflow = document.body.style.overflow;
        const elementos = [document.getElementById('app')];
        const estados = elementos.map((item) => item.inert);
        elementos.forEach((item) => { item.inert = true; });
        document.body.style.overflow = 'hidden';
        ref.current.focus();
        return () => {
            elementos.forEach((item, i) => { item.inert = estados[i]; });
            document.body.style.overflow = overflow;
            if (anterior?.isConnected) anterior.focus();
        };
    }, []);
    return createPortal(<div className="bloqueio-autenticacao" ref={ref} role="dialog" aria-modal="true" aria-busy="true"
        aria-label={rotulo} tabIndex={-1}
        onKeyDown={(event) => { if (event.key === 'Tab') event.preventDefault(); }}>
        <i className="fa-solid fa-spinner fa-spin" aria-hidden="true" />
        <span className="sr-only" role="status">{rotulo}</span>
    </div>, document.body);
}
