import { useEffect, useId, useRef, useState } from 'react';

export default function ControleQuantidade({ valor, alterar, desabilitado = false, produto = 'produto', validadeAlterada }) {
    const id = useId();
    const [texto, setTexto] = useState(String(valor));
    const [erro, setErro] = useState('');
    const confirmando = useRef(false);
    useEffect(() => { setTexto(String(valor)); setErro(''); validadeAlterada?.(true); }, [valor, validadeAlterada]);

    const confirmar = async (entrada) => {
        if (desabilitado || confirmando.current) return;
        const numero = Number(entrada);
        if (!String(entrada).trim() || !Number.isFinite(numero) || numero < 0.001) {
            setErro('Informe uma quantidade maior que zero.'); validadeAlterada?.(false); return;
        }
        const proximo = Math.round(numero * 1000) / 1000;
        setTexto(String(proximo)); setErro(''); validadeAlterada?.(true);
        if (proximo === Number(valor)) return;
        confirmando.current = true;
        try { await alterar(proximo); }
        catch { setTexto(String(valor)); }
        finally { confirmando.current = false; }
    };
    const atual = Number(texto) || Number(valor);
    return <div className="quantidade-campo">
        <label htmlFor={id}>Quantidade</label>
        <div className="quantidade-controle">
            <button type="button" aria-label={`Diminuir quantidade de ${produto}`} disabled={desabilitado || atual <= 1} onPointerDown={(event) => event.preventDefault()} onClick={() => confirmar(atual - 1)}><i className="fa-solid fa-minus" aria-hidden="true" /></button>
            <input id={id} aria-label={`Quantidade de ${produto}`} aria-invalid={Boolean(erro)} aria-describedby={erro ? `${id}-erro` : undefined} type="number" min="0.001" step="0.001" value={texto} disabled={desabilitado} onChange={(event) => {
                const entrada = event.target.value;
                setTexto(entrada);
                validadeAlterada?.(entrada.trim() !== '' && Number.isFinite(Number(entrada)) && Number(entrada) >= 0.001);
            }} onBlur={() => confirmar(texto)} onKeyDown={(event) => {
                if (event.key === 'Enter') { event.preventDefault(); confirmar(texto); }
                if (event.key === 'Escape') { setTexto(String(valor)); setErro(''); validadeAlterada?.(true); }
            }} />
            <button type="button" aria-label={`Aumentar quantidade de ${produto}`} disabled={desabilitado} onPointerDown={(event) => event.preventDefault()} onClick={() => confirmar(atual + 1)}><i className="fa-solid fa-plus" aria-hidden="true" /></button>
        </div>
        {erro && <p id={`${id}-erro`} role="alert">{erro}</p>}
    </div>;
}
