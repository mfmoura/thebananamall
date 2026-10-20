import { useEffect, useRef, useState } from 'react';
import { carregarMercadoPago } from '../services/mercadoPago';

export default function useCartaoMercadoPago({ publicKey, total, email, aoTokenizar }) {
    const [pronto, setPronto] = useState(false);
    const [erro, setErro] = useState(null);
    const [tokenizando, setTokenizando] = useState(false);
    const [exigeEmissor, setExigeEmissor] = useState(false);
    const [bandeira, setBandeira] = useState(null);
    const [tentativa, setTentativa] = useState(0);
    const [falhaCarregamento, setFalhaCarregamento] = useState(false);
    const callback = useRef(aoTokenizar);
    callback.current = aoTokenizar;
    useEffect(() => {
        let ativo = true;
        let cardForm;
        let timeout;
        let inicializacaoFalhou = false;
        let camposProntos = false;
        const documentosDisponiveis = () => Array.from(document.getElementById('mp-documento-tipo')?.options || []).some((opcao) => opcao.value);
        const falhar = (mensagem) => {
            clearTimeout(timeout);
            inicializacaoFalhou = true;
            if (ativo) { setErro(new Error(mensagem)); setPronto(false); setTokenizando(false); setFalhaCarregamento(true); }
        };
        setPronto(false); setErro(null); setBandeira(null); setExigeEmissor(false); setFalhaCarregamento(false); setTokenizando(false);
        carregarMercadoPago().then((MercadoPago) => {
            if (!ativo) return;
            // Arma antes da montagem: onReady também pode ser chamado durante a inicialização.
            timeout = window.setTimeout(() => {
                falhar(documentosDisponiveis()
                    ? 'Os campos seguros demoraram para carregar. Confira a conexão ou o bloqueador de conteúdo e tente carregar novamente.'
                    : 'O Mercado Pago não concluiu o carregamento dos tipos de documento do titular. Tente carregar os campos novamente.');
                cardForm?.unmount();
                cardForm = null;
            }, 30000);
            const mp = new MercadoPago(publicKey, { locale: 'pt-BR' });
            cardForm = mp.cardForm({
                amount: String(total), iframe: true,
                form: {
                    id: 'mp-cartao',
                    cardNumber: { id: 'mp-numero', placeholder: '0000 0000 0000 0000', style: { fontSize: '16px', fontFamily: 'Arial', color: '#382b29' } },
                    expirationDate: { id: 'mp-validade', placeholder: 'MM/AA', mode: 'short', style: { fontSize: '16px', fontFamily: 'Arial', color: '#382b29' } },
                    securityCode: { id: 'mp-cvv', placeholder: 'CVV', style: { fontSize: '16px', fontFamily: 'Arial', color: '#382b29' } },
                    cardholderName: { id: 'mp-titular' },
                    cardholderEmail: { id: 'mp-email' },
                    identificationType: { id: 'mp-documento-tipo' },
                    identificationNumber: { id: 'mp-documento' },
                    issuer: { id: 'mp-emissor' },
                    installments: { id: 'mp-parcelas' },
                },
                callbacks: {
                    onFormMounted: (falha) => { if (ativo && falha) falhar('Não foi possível montar os campos seguros. Tente carregar novamente.'); },
                    onIdentificationTypesReceived: (falha, tipos) => {
                        if (ativo && (falha || !Array.isArray(tipos) || !tipos.some((tipo) => typeof tipo.id === 'string' && tipo.id))) {
                            falhar('Não foi possível carregar os tipos de documento do titular. Tente carregar os campos novamente.');
                        }
                    },
                    onReady: () => {
                        if (!ativo || inicializacaoFalhou || camposProntos) return;
                        if (!documentosDisponiveis()) {
                            falhar('O tipo de documento do titular não foi preenchido pelo Mercado Pago. Tente carregar os campos novamente.');
                            return;
                        }
                        const montados = ['mp-numero', 'mp-validade', 'mp-cvv'].every((id) => {
                            const host = document.getElementById(id);
                            const frames = host?.querySelectorAll('iframe');
                            const dimensoes = host?.getBoundingClientRect();
                            return frames?.length === 1 && dimensoes.width > 0 && dimensoes.height > 0
                                && document.getElementById(`${id}-label`)?.getClientRects().length > 0
                                && getComputedStyle(host).pointerEvents !== 'none' && getComputedStyle(frames[0]).pointerEvents !== 'none';
                        });
                        if (!montados) { falhar('Os campos seguros não carregaram. Confira o bloqueador de conteúdo e recarregue a página.'); return; }
                        camposProntos = true;
                        clearTimeout(timeout); setPronto(true); setErro(null);
                    },
                    onFetching: (recurso) => {
                        if (recurso === 'cardToken' && ativo) setTokenizando(true);
                        return () => { if (ativo) setTokenizando(false); };
                    },
                    onPaymentMethodsReceived: (falha, dados) => {
                        if (!ativo || !camposProntos || inicializacaoFalhou) return;
                        if (falha) { setBandeira(null); setExigeEmissor(false); return; }
                        const metodos = Array.isArray(dados) ? dados : dados?.results || [];
                        const metodo = metodos[0];
                        setBandeira(metodo ? { id: metodo.id, nome: metodo.name } : null);
                        setExigeEmissor(metodos.some((metodo) => metodo.additional_info_needed?.includes('issuer_id')));
                    },
                    onBinChange: () => {
                        if (ativo) { setBandeira(null); setExigeEmissor(false); }
                    },
                    onError: () => {
                        if (ativo && !inicializacaoFalhou) { setErro(new Error('Confira os dados do cartão e do titular e tente novamente.')); setTokenizando(false); }
                    },
                    onSubmit: async (event) => {
                        event.preventDefault();
                        if (!ativo || !camposProntos || inicializacaoFalhou) return;
                        setTokenizando(false); setErro(null);
                        const { token, paymentMethodId } = cardForm.getCardFormData();
                        if (!token || !paymentMethodId) { setErro(new Error('Confira os dados do cartão para continuar.')); return; }
                        // Somente o token temporário e o identificador do meio saem do SDK.
                        try { await callback.current({ token, payment_method_id: paymentMethodId, parcelas: 1 }); }
                        catch { if (ativo) setErro(new Error('Não foi possível concluir. Consulte o status antes de tentar novamente.')); }
                    },
                },
            });
        }).catch(() => falhar('Não foi possível carregar os campos seguros. Confira a conexão ou o bloqueador de conteúdo e tente carregar novamente.'));
        return () => { ativo = false; clearTimeout(timeout); cardForm?.unmount(); };
    }, [publicKey, total, email, tentativa]);
    return { pronto, erro, tokenizando, exigeEmissor, bandeira, falhaCarregamento, recarregar: () => setTentativa((valor) => valor + 1) };
}
