import { MensagemErro } from './Formulario';
import useCartaoMercadoPago from '../hooks/useCartaoMercadoPago';

export default function PagamentoCartao({ publicKey, pedido, email, enviar, ocupado }) {
    const { pronto, erro, tokenizando, exigeEmissor } = useCartaoMercadoPago({ publicKey, total: pedido.total, email, aoTokenizar: enviar });
    return <section><h2>Dados do cartão</h2><MensagemErro erro={erro} />
        {!pronto && !erro && <p role="status">Carregando campos seguros…</p>}
        <form id="mp-cartao" className="formulario" data-mp-public-key-source="runtime-endpoint" data-mp-payer-email-source="application" data-mp-payer-identification-source="form" aria-busy={ocupado || tokenizando} onSubmit={(event) => event.preventDefault()}>
            <div data-mp-field="cardNumber" role="group" aria-labelledby="mp-numero-label"><label id="mp-numero-label">Número do cartão</label><div id="mp-numero" data-mp-secure-field="cardNumber" className="mp-campo-seguro" aria-labelledby="mp-numero-label" /></div>
            <div className="mp-campos-linha"><div data-mp-field="expirationDate" role="group" aria-labelledby="mp-validade-label"><label id="mp-validade-label">Validade</label><div id="mp-validade" data-mp-secure-field="expirationDate" className="mp-campo-seguro" aria-labelledby="mp-validade-label" /></div>
                <div data-mp-field="securityCode" role="group" aria-labelledby="mp-cvv-label"><label id="mp-cvv-label">Código de segurança</label><div id="mp-cvv" data-mp-secure-field="securityCode" className="mp-campo-seguro" aria-labelledby="mp-cvv-label" /></div></div>
            <label data-mp-field="cardholderName" htmlFor="mp-titular">Nome no cartão<input id="mp-titular" autoComplete="cc-name" required maxLength={100} /></label>
            <label data-mp-field="identificationNumber" htmlFor="mp-documento">Documento do titular<input id="mp-documento" inputMode="numeric" required autoComplete="off" maxLength={18} /></label>
            <input id="mp-email" type="hidden" value={email} readOnly />
            <label htmlFor="mp-emissor" hidden={!exigeEmissor}>Banco emissor<select id="mp-emissor" data-mp-sdk-required-field="issuer" /></label>
            <select id="mp-parcelas" data-mp-sdk-required-field="installments" hidden aria-hidden="true" tabIndex={-1} />
            <label data-mp-field="identificationType" htmlFor="mp-documento-tipo">Tipo de documento do titular<select id="mp-documento-tipo" data-mp-sdk-required-field="identificationType" /></label>
            <p>Pagamento à vista. Seus dados do cartão são enviados diretamente ao Mercado Pago.</p>
            <button type="submit" className="botao-principal" disabled={!pronto || ocupado || tokenizando}>{ocupado || tokenizando ? 'Processando…' : 'Pagar com cartão'}</button>
        </form>
    </section>;
}
