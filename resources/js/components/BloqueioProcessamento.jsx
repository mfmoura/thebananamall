import { useLayoutEffect, useRef } from 'react';
import { createPortal } from 'react-dom';

export default function BloqueioProcessamento({ rotulo = 'Processando, aguarde' }) {
    const ref = useRef(null);
    useLayoutEffect(() => {
        const dialogo = ref.current;
        const anterior = document.activeElement;
        const overflow = document.body.style.overflow;
        const elementos = [document.getElementById('app')];
        const estados = elementos.map((item) => item.inert);
        elementos.forEach((item) => { item.inert = true; });
        document.body.style.overflow = 'hidden';
        dialogo.showModal();
        dialogo.focus();
        return () => {
            dialogo.close();
            elementos.forEach((item, i) => { item.inert = estados[i]; });
            document.body.style.overflow = overflow;
            if (anterior?.isConnected) anterior.focus();
        };
    }, []);
    return createPortal(<dialog className="bloqueio-autenticacao" ref={ref} aria-modal="true" aria-busy="true"
        aria-label={rotulo} tabIndex={-1}
        onCancel={(event) => event.preventDefault()}
        onKeyDown={(event) => { if (event.key === 'Tab') event.preventDefault(); }}>
        <i className="fa-solid fa-spinner fa-spin" aria-hidden="true" />
        <span className="sr-only" role="status">{rotulo}</span>
    </dialog>, document.body);
}
