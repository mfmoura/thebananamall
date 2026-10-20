import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api } from '../services/api';
import { useLoja } from '../context/LojaContext';
import useDados from '../hooks/useDados';
import { MensagemErro } from '../components/Formulario';
import BloqueioAutenticacao from '../components/BloqueioAutenticacao';
import PaginaSkeleton from '../components/PaginaSkeleton';
import PagamentoCartao from '../components/PagamentoCartao';
import { estadoPagamento } from '../services/estadoPagamento';
import TempoPagamento from '../components/TempoPagamento';

const meios = { pix: 'pix', boleto: 'boleto', cartao_credito: 'credit_card', cartao_debito: 'debit_card' };

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
    const pixIniciado = useRef(new Set());
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
    const meio = meios[pedido?.forma_pagamento?.codigo];
    const visual = estadoPagamento(pedido, pagamento, meio);
    const { confirmado, encerrado } = visual;
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
        if (!pagamento?.id || confirmado || encerrado || visual.verificacao) return;
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
    }, [pagamento?.id, confirmado, encerrado, visual.verificacao, atualizar]);

    const enviar = useCallback(async (dadosPagamento) => {
        if (envioEmCurso.current) return;
        envioEmCurso.current = true; setOcupado(true); setIniciou(true); setFalha(null);
        mesmaTentativa.current ??= dadosPagamento;
        try {
            const cobranca = await api.criarPagamento(id, mesmaTentativa.current);
            mesmaTentativa.current = null;
            setAtual({ pedido, pagamento: cobranca });
            if (!['pix', 'boleto'].includes(dadosPagamento.meio)) navigate(`/pedidos/${id}`);
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
    }, [atualizar, id, navigate, pedido]);

    useEffect(() => {
        if (carregando || erro || meio !== 'pix' || pedido?.status !== 'aguardando_pagamento'
            || pagamento || confirmado || encerrado || iniciou || pixIniciado.current.has(id)) return;
        pixIniciado.current.add(id);
        enviar({ meio: 'pix' });
    }, [carregando, erro, meio, pedido?.status, pagamento, confirmado, encerrado, iniciou, id, enviar]);

    if (carregando) return <PaginaSkeleton rotulo="Carregando pagamento" />;
    if (erro) return <section className="tela"><MensagemErro erro={erro} /><Link to={`/pedidos/${id}`}>Consultar pedido</Link></section>;
    const instrucoes = pagamento?.instrucoes || {};
    const boletoUrl = (() => {
        try { const url = new URL(instrucoes.ticket_url); return url.protocol === 'https:' ? url.href : null; }
        catch { return null; }
    })();
    const qr = typeof instrucoes.qr_code_base64 === 'string' && /^[A-Za-z0-9+/=\r\n]+$/.test(instrucoes.qr_code_base64) ? instrucoes.qr_code_base64 : null;
    return <section className="tela tela-form"><h1>Pagamento do pedido #{pedido.id}</h1>
        {ocupado && <BloqueioAutenticacao rotulo="Enviando solicitação de pagamento" />}
        <p>Frete: {pedido.frete?.nome || "Não informado"} · {Number(pedido.custo_frete ?? 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" })}</p>{pedido.frete?.prazo && <p>Entrega: {pedido.frete.prazo}</p>}<p className="mp-total">Total: <strong>{Number(pedido.total).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</strong></p>
        <p>Forma de pagamento: {pedido.forma_pagamento?.nome}</p><MensagemErro erro={falha} />
        {confirmado || encerrado ? <div className="mensagem" role="status"><h2>{visual.titulo}</h2><p>{visual.mensagem}</p></div>
                : pagamento || iniciou || meio === 'pix' ? <>
                    <h2 role="status">{visual.titulo}</h2>
                    {pagamento?.id && !visual.verificacao && <TempoPagamento key={pagamento.id} pagamento={pagamento} />}
                    {pagamento?.order_id && <p>Identificador da cobrança: {pagamento.order_id}</p>}
                    {meio === 'pix' && !visual.verificacao && (qr || instrucoes.qr_code) && <>
                        <p>Abra o aplicativo do seu banco e use o QR Code ou o Pix copia e cola. Confira o valor e o destinatário antes de pagar. Aguarde a confirmação neste pedido.</p>
                        {qr && <img className="mp-qr" src={`data:image/png;base64,${qr}`} alt="QR Code Pix do pedido" width="240" height="240" />}
                        {instrucoes.qr_code && <><div className="boleto-linha"><label htmlFor="pix-copia-cola">Pix copia e cola</label><div className="boleto-linha-campo"><textarea id="pix-copia-cola" value={instrucoes.qr_code} readOnly rows={3} /><button className="boleto-linha-copiar" type="button" aria-label="Copiar código Pix" title="Copiar código Pix" onClick={async () => {
                            try { await navigator.clipboard.writeText(instrucoes.qr_code); setCopiado(true); }
                            catch { setFalha(new Error('Não foi possível copiar automaticamente. Selecione e copie o código no campo acima.')); }
                        }}><i className="fa-solid fa-copy" aria-hidden="true" /></button></div></div>{copiado && <p role="status">Código copiado.</p>}</>}
                    </>}
                    {meio === 'boleto' && !visual.verificacao && (instrucoes.digitable_line || boletoUrl) && <>
                        {instrucoes.digitable_line && <><div className="boleto-linha"><label htmlFor="boleto-linha-digitavel">Linha digitável</label><div className="boleto-linha-campo"><textarea id="boleto-linha-digitavel" value={instrucoes.digitable_line} readOnly rows={3} /><button className="boleto-linha-copiar" type="button" aria-label="Copiar linha digitável" title="Copiar linha digitável" onClick={async () => {
                            try { await navigator.clipboard.writeText(instrucoes.digitable_line); setCopiado(true); }
                            catch { setFalha(new Error('Selecione e copie a linha digitável no campo acima.')); }
                        }}><i className="fa-solid fa-copy" aria-hidden="true" /></button></div></div>{copiado && <p role="status">Linha digitável copiada.</p>}</>}
                        {boletoUrl && <p><a href={boletoUrl} target="_blank" rel="noopener noreferrer">Abrir / imprimir boleto</a></p>}
                        <p>Use a linha digitável no aplicativo do seu banco ou abra o boleto para imprimir. Confira o valor, o beneficiário e o vencimento antes de pagar. A emissão do boleto não confirma o pagamento; o pedido será confirmado após a compensação.</p>
                    </>}
                    <p role="status">{pagamento?.processamento === 'verificacao_necessaria'
                        ? 'O resultado precisa de verificação. Entre em contato com a loja antes de tentar outro pagamento.'
                        : pagamento?.order_id ? 'A confirmação depende do retorno do Mercado Pago. Você pode sair desta tela e acompanhar pelo pedido.'
                            : 'Solicitação recebida. Estamos preparando o pagamento em segundo plano. Você pode continuar navegando e acompanhar pelo pedido.'}</p>
                    {!pagamento?.id && falha && !ocupado && (mesmaTentativa.current || ['pix', 'boleto'].includes(meio)) && <button type="button" onClick={() => enviar(mesmaTentativa.current || { meio })}>Retomar a mesma tentativa</button>}
                </> : ['pix', 'boleto'].includes(meio) ? <button type="button" className="botao-principal" disabled={ocupado} onClick={() => enviar({ meio })}>Gerar {meio === 'pix' ? 'Pix' : 'boleto'}</button>
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
