import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '../services/api';
import { useLoja } from '../context/LojaContext';
import useDados from '../hooks/useDados';
import { MensagemErro } from '../components/Formulario';
import BloqueioAutenticacao from '../components/BloqueioAutenticacao';
import PagamentoCartao from '../components/PagamentoCartao';

const meios = { pix: 'pix', cartao_credito: 'credit_card', cartao_debito: 'debit_card' };
const finalizados = ['failed', 'canceled', 'cancelled', 'expired', 'refunded', 'charged_back'];
const pagos = ['pago', 'em_processamento', 'enviado', 'entregue'];

export default function Pagamento() {
    const { id } = useParams();
    const { usuario } = useLoja();
    const navigate = useNavigate();
    const [atual, setAtual] = useState(null);
    const [falha, setFalha] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const [iniciou, setIniciou] = useState(false);
    const [copiado, setCopiado] = useState(false);
    // Token apenas em memória enquanto o resultado for incerto, para repetir exatamente a mesma tentativa.
    const mesmaTentativa = useRef(null);
    const envioEmCurso = useRef(false);
    const consultaEmCurso = useRef(false);
    const { dados, erro, carregando } = useDados(async () => {
        const pedido = await api.pedido(id);
        let pagamento;
        try { pagamento = await api.consultarPagamento(id); }
        catch (e) { if (e.status !== 404) throw e; pagamento = null; }
        return { pedido: pagamento ? await api.pedido(id) : pedido, pagamento };
    }, id);
    const estado = atual || dados;
    const pedido = estado?.pedido;
    const pagamento = estado?.pagamento;
    const confirmado = pagos.includes(pedido?.status);
    const encerrado = finalizados.includes(pagamento?.status) || pedido?.status === 'cancelado'
        || (pagamento?.processamento === 'concluido' && !confirmado);
    const meio = meios[pedido?.forma_pagamento?.codigo];
    const atualizar = useCallback(async () => {
        if (consultaEmCurso.current) return;
        consultaEmCurso.current = true;
        try {
            const cobranca = await api.consultarPagamento(id);
            const registro = await api.pedido(id);
            setAtual({ pedido: registro, pagamento: cobranca });
            setFalha(null);
        } finally { consultaEmCurso.current = false; }
    }, [id]);
    useEffect(() => {
        if (!pagamento?.id || confirmado || encerrado) return;
        let ativo = true;
        let timer;
        let consultas = 0;
        const consultar = async () => {
            if (!ativo) return;
            if (!document.hidden) {
                consultas++;
                try { await atualizar(); } catch (e) { if (ativo) setFalha(e); }
            }
            if (ativo && consultas < 60) timer = setTimeout(consultar, 10000);
        };
        timer = setTimeout(consultar, 10000);
        return () => { ativo = false; clearTimeout(timer); };
    }, [pagamento?.id, confirmado, encerrado, atualizar]);

    const enviar = async (dadosPagamento) => {
        if (envioEmCurso.current) return;
        envioEmCurso.current = true; setOcupado(true); setIniciou(true); setFalha(null);
        mesmaTentativa.current ??= dadosPagamento;
        try {
            const cobranca = await api.criarPagamento(id, mesmaTentativa.current);
            mesmaTentativa.current = null;
            setAtual({ pedido, pagamento: cobranca });
            if (dadosPagamento.meio !== 'pix') navigate(`/pedidos/${id}`);
        } catch (e) {
            try { await atualizar(); } catch { /* Mantém a tentativa para consulta/retentativa explícita. */ }
            if ([422, 403, 401].includes(e.status)) {
                mesmaTentativa.current = null;
                if (e.status === 422) {
                    try { await api.consultarPagamento(id); }
                    catch (consulta) { if (consulta.status === 404) setIniciou(false); }
                }
            }
            setFalha(e);
        } finally { envioEmCurso.current = false; setOcupado(false); }
    };

    if (carregando) return <BloqueioAutenticacao rotulo="Carregando pagamento" />;
    if (erro) return <section className="tela"><MensagemErro erro={erro} /><Link to={`/pedidos/${id}`}>Consultar pedido</Link></section>;
    const instrucoes = pagamento?.instrucoes || {};
    const qr = typeof instrucoes.qr_code_base64 === 'string' && /^[A-Za-z0-9+/=\r\n]+$/.test(instrucoes.qr_code_base64) ? instrucoes.qr_code_base64 : null;
    return <section className="tela tela-form"><h1>Pagamento do pedido #{pedido.id}</h1>
        <p className="mp-total">Total: <strong>{Number(pedido.total).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</strong></p>
        <p>Forma de pagamento: {pedido.forma_pagamento?.nome}</p><MensagemErro erro={falha} />
        {confirmado ? <div className="mensagem" role="status"><h2>Pagamento confirmado</h2><p>Recebemos a confirmação do pagamento do pedido #{pedido.id}.</p></div>
            : encerrado ? <div className="mensagem" role="status"><h2>Pagamento encerrado</h2><p>Este pagamento não está disponível para novas tentativas. Consulte o pedido ou entre em contato com a loja.</p></div>
                : pagamento || iniciou ? <>
                    <h2>{meio === 'pix' ? 'Aguardando pagamento por Pix' : 'Acompanhamento do pagamento'}</h2>
                    {pagamento?.order_id && <p>Identificador da cobrança: {pagamento.order_id}</p>}
                    {meio === 'pix' && <>
                        {qr && <img className="mp-qr" src={`data:image/png;base64,${qr}`} alt="QR Code Pix do pedido" width="240" height="240" />}
                        {instrucoes.qr_code && <><label className="formulario">Pix copia e cola<textarea value={instrucoes.qr_code} readOnly rows={4} /></label><button type="button" onClick={async () => {
                            try { await navigator.clipboard.writeText(instrucoes.qr_code); setCopiado(true); }
                            catch { setFalha(new Error('Não foi possível copiar automaticamente. Selecione e copie o código no campo acima.')); }
                        }}>Copiar código Pix</button>{copiado && <p role="status">Código copiado.</p>}</>}
                    </>}
                    <p role="status">{pagamento?.processamento === 'verificacao_necessaria'
                        ? 'O resultado precisa de verificação. Entre em contato com a loja antes de tentar outro pagamento.'
                        : pagamento?.order_id ? 'A confirmação depende do retorno do Mercado Pago. Você pode sair desta tela e acompanhar pelo pedido.'
                            : 'Solicitação recebida. Estamos preparando o pagamento em segundo plano. Você pode continuar navegando e acompanhar pelo pedido.'}</p>
                    <button type="button" disabled={ocupado} onClick={() => atualizar().catch(setFalha)}>Atualizar pagamento</button>
                    {!pagamento?.id && (mesmaTentativa.current || meio === 'pix') && <button type="button" disabled={ocupado} onClick={() => enviar(mesmaTentativa.current || { meio: 'pix' })}>Retomar a mesma tentativa</button>}
                </> : meio === 'pix' ? <button type="button" className="botao-principal" disabled={ocupado} onClick={() => enviar({ meio: 'pix' })}>Gerar Pix</button>
                    : meio ? <Cartao pedido={pedido} email={usuario.email} enviar={(cartao) => enviar({ meio, ...cartao })} ocupado={ocupado} />
                        : <p>Esta forma de pagamento não está disponível no checkout.</p>}
        <p><Link to={`/pedidos/${pedido.id}`}>Ver pedido</Link></p>
        <p><Link to="/">Continuar comprando</Link></p>
    </section>;
}

function Cartao(props) {
    const { dados, erro, carregando } = useDados(api.configuracaoPagamento, 'mp-config');
    if (carregando) return <p role="status">Preparando pagamento seguro…</p>;
    if (erro) return <MensagemErro erro={erro} />;
    return <PagamentoCartao {...props} publicKey={dados.public_key} />;
}
