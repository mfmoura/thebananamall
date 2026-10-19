import { useEffect, useState } from 'react';
import { MensagemErro } from './Formulario';
import useCartaoMercadoPago from '../hooks/useCartaoMercadoPago';
import { formatosDocumentoCartao, formatarDocumentoCartao, normalizarDocumentoCartao } from '../services/documentoCartao';

const iconesBandeiras = { visa: 'fa-cc-visa', master: 'fa-cc-mastercard', amex: 'fa-cc-amex', diners: 'fa-cc-diners-club', discover: 'fa-cc-discover', jcb: 'fa-cc-jcb' };

export default function PagamentoCartao({ publicKey, pedido, email, enviar, ocupado }) {
    const { pronto, erro, tokenizando, exigeEmissor, bandeira } = useCartaoMercadoPago({ publicKey, total: pedido.total, email, aoTokenizar: enviar });
    const [tipoDocumento, setTipoDocumento] = useState('');
    const [documento, setDocumento] = useState('');
    useEffect(() => {
        setTipoDocumento(pronto ? document.getElementById('mp-documento-tipo')?.value || '' : '');
        setDocumento('');
    }, [pronto]);
    const formatoDocumento = formatosDocumentoCartao[tipoDocumento];
    const iconeBandeira = iconesBandeiras[bandeira?.id];
    return <section className="pagamento-cartao"><h2>Dados do cartão</h2>
        <MensagemErro erro={erro} />
        {!pronto && !erro && <p role="status">Carregando campos seguros…</p>}
        <form id="mp-cartao" className="formulario" data-mp-public-key-source="runtime-endpoint" data-mp-payer-email-source="application" data-mp-payer-identification-source="form" aria-busy={ocupado || tokenizando} onSubmit={(event) => event.preventDefault()}>
            <div data-mp-field="cardNumber" role="group" aria-labelledby="mp-numero-label"><label id="mp-numero-label">Número do cartão</label><div className="cartao-numero-linha">
                <div id="mp-numero" data-mp-secure-field="cardNumber" className="mp-campo-seguro" aria-labelledby="mp-numero-label" />
                <span className="cartao-bandeira" role="status" aria-live="polite" aria-atomic="true">
                    <i className={iconeBandeira ? `fa-brands ${iconeBandeira}` : 'fa-regular fa-credit-card'} aria-hidden="true" />
                    {bandeira ? <span>{bandeira.nome}</span> : <span className="sr-only">Bandeira ainda não identificada</span>}
                </span>
            </div></div>
            <div className="mp-campos-linha"><div data-mp-field="expirationDate" role="group" aria-labelledby="mp-validade-label"><label id="mp-validade-label">Validade</label><div id="mp-validade" data-mp-secure-field="expirationDate" className="mp-campo-seguro" aria-labelledby="mp-validade-label" /></div>
                <div data-mp-field="securityCode" role="group" aria-labelledby="mp-cvv-label"><label id="mp-cvv-label">Código de segurança</label><div id="mp-cvv" data-mp-secure-field="securityCode" className="mp-campo-seguro" aria-labelledby="mp-cvv-label" /></div></div>
            <label data-mp-field="cardholderName" htmlFor="mp-titular">Nome no cartão<input id="mp-titular" autoComplete="cc-name" required maxLength={100} /></label>
            <label data-mp-field="identificationNumber" htmlFor="documento-titular">Documento do titular<input id="documento-titular" value={formatarDocumentoCartao(tipoDocumento, documento)} onChange={(event) => setDocumento(normalizarDocumentoCartao(tipoDocumento, event.target.value))} inputMode={formatoDocumento ? 'numeric' : 'text'} required autoComplete="off" placeholder={formatoDocumento || 'Número do documento'} maxLength={formatoDocumento?.length || 30} minLength={formatoDocumento?.length} /></label>
            {/* O SDK recebe o documento sem a pontuação da máscara. */}
            <input id="mp-documento" type="hidden" value={documento} readOnly />
            <input id="mp-email" type="hidden" value={email} readOnly />
            <label htmlFor="mp-emissor" hidden={!exigeEmissor}>Banco emissor<select id="mp-emissor" data-mp-sdk-required-field="issuer" /></label>
            <select id="mp-parcelas" data-mp-sdk-required-field="installments" hidden aria-hidden="true" tabIndex={-1} />
            <label data-mp-field="identificationType" htmlFor="mp-documento-tipo">Tipo de documento do titular<select id="mp-documento-tipo" data-mp-sdk-required-field="identificationType" onChange={(event) => { setTipoDocumento(event.target.value); setDocumento(''); }} /></label>
            <p>Pagamento à vista. Seus dados do cartão são enviados diretamente ao Mercado Pago.</p>
            <button type="submit" className="botao-principal" disabled={!pronto || ocupado || tokenizando}>{ocupado || tokenizando ? 'Processando…' : 'Pagar com cartão'}</button>
        </form>
    </section>;
}
