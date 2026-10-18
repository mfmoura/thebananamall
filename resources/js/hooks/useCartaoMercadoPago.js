import { useEffect, useRef, useState } from 'react';
import { carregarMercadoPago } from '../services/mercadoPago';

export default function useCartaoMercadoPago({ publicKey, total, email, aoTokenizar }) {
    const [pronto, setPronto] = useState(false);
    const [erro, setErro] = useState(null);
    const [tokenizando, setTokenizando] = useState(false);
    const [exigeEmissor, setExigeEmissor] = useState(false);
    const callback = useRef(aoTokenizar);
    callback.current = aoTokenizar;
    useEffect(() => {
        let ativo = true;
        let cardForm;
        let timeout;
        const falhar = (mensagem) => { if (ativo) { setErro(new Error(mensagem)); setPronto(false); setTokenizando(false); } };
        setPronto(false); setErro(null);
        carregarMercadoPago().then((MercadoPago) => {
            if (!ativo) return;
            const mp = new MercadoPago(publicKey, { locale: 'pt-BR' });
            cardForm = mp.cardForm({
                amount: String(total), iframe: true,
                form: {
                    id: 'mp-cartao',
                    cardNumber: { id: 'mp-numero', placeholder: 'Número do cartão' },
                    expirationDate: { id: 'mp-validade', placeholder: 'MM/AA' },
                    securityCode: { id: 'mp-cvv', placeholder: 'CVV' },
                    cardholderName: { id: 'mp-titular' },
                    cardholderEmail: { id: 'mp-email' },
                    identificationType: { id: 'mp-documento-tipo' },
                    identificationNumber: { id: 'mp-documento' },
                    issuer: { id: 'mp-emissor' },
                    installments: { id: 'mp-parcelas' },
                },
                callbacks: {
                    onFormMounted: (falha) => { if (falha) falhar('Não foi possível montar os campos seguros. Recarregue a página.'); },
                    onReady: () => {
                        if (!ativo) return;
                        const montados = ['mp-numero', 'mp-validade', 'mp-cvv'].every((id) => {
                            const host = document.getElementById(id);
                            const frames = host?.querySelectorAll('iframe');
                            const dimensoes = host?.getBoundingClientRect();
                            return frames?.length === 1 && dimensoes.width > 0 && dimensoes.height > 0
                                && document.getElementById(`${id}-label`)?.getClientRects().length > 0
                                && getComputedStyle(host).pointerEvents !== 'none' && getComputedStyle(frames[0]).pointerEvents !== 'none';
                        });
                        if (!montados) { falhar('Os campos seguros não carregaram. Confira o bloqueador de conteúdo e recarregue a página.'); return; }
                        clearTimeout(timeout); setPronto(true); setErro(null);
                    },
                    onFetching: (recurso) => {
                        if (recurso === 'cardToken' && ativo) setTokenizando(true);
                        return () => { if (ativo) setTokenizando(false); };
                    },
                    onPaymentMethodsReceived: (falha, dados) => {
                        if (!ativo || falha) return;
                        const metodos = Array.isArray(dados) ? dados : dados?.results || [];
                        setExigeEmissor(metodos.some((metodo) => metodo.additional_info_needed?.includes('issuer_id')));
                    },
                    onError: () => {
                        if (ativo) { setErro(new Error('Confira os dados do cartão e do titular e tente novamente.')); setTokenizando(false); }
                    },
                    onSubmit: async (event) => {
                        event.preventDefault();
                        if (!ativo) return;
                        setTokenizando(false); setErro(null);
                        const { token, paymentMethodId } = cardForm.getCardFormData();
                        if (!token || !paymentMethodId) { setErro(new Error('Confira os dados do cartão para continuar.')); return; }
                        // Somente o token temporário e o identificador do meio saem do SDK.
                        try { await callback.current({ token, payment_method_id: paymentMethodId, parcelas: 1 }); }
                        catch { if (ativo) setErro(new Error('Não foi possível concluir. Consulte o status antes de tentar novamente.')); }
                    },
                },
            });
            timeout = window.setTimeout(() => falhar('Os campos seguros demoraram para carregar. Recarregue a página para tentar novamente.'), 15000);
        }).catch((falha) => { if (ativo) setErro(falha); });
        return () => { ativo = false; clearTimeout(timeout); cardForm?.unmount(); };
    }, [publicKey, total, email]);
    return { pronto, erro, tokenizando, exigeEmissor };
}
