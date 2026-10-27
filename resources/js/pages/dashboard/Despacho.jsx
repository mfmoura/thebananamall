import { useState } from 'react';
import PedidosAdmin from './Pedidos';

const exemplos = [
    { id: 'DEMO-101', cliente: 'Cliente de exemplo A', cidade: 'São Paulo / SP', nota: '000101', itens: [{ nome: 'Bandeira', quantidade: 2 }, { nome: 'Adesivo', quantidade: 3 }] },
    { id: 'DEMO-102', cliente: 'Cliente de exemplo B', cidade: 'Campinas / SP', nota: '000102', itens: [{ nome: 'Camiseta · M', quantidade: 1 }] },
    { id: 'DEMO-103', cliente: 'Cliente de exemplo C', cidade: 'Curitiba / PR', nota: '000103', itens: [{ nome: 'Bandeira', quantidade: 1 }] },
];

export default function Despacho() {
    const [modo, setModo] = useState('simulacao');
    const [selecionado, setSelecionado] = useState(exemplos[0].id);
    const [etapas, setEtapas] = useState({});
    const [aviso, setAviso] = useState('');
    const pedido = exemplos.find((item) => item.id === selecionado);
    const pendentes = exemplos.filter((item) => etapas[item.id] !== 'despachado');
    const etapa = etapas[selecionado] || 'aguardando';
    const nomesEtapas = { aguardando: 'Aguardando separação', separado: 'Separado e conferido', despachado: 'Despacho simulado' };

    return <>
        <div className="dashboard-acoes" aria-label="Modo da tela de despacho">
            <button aria-pressed={modo === 'simulacao'} onClick={() => setModo('simulacao')}>Ver demonstração</button>
            <button aria-pressed={modo === 'real'} onClick={() => setModo('real')}>Consultar pedidos reais</button>
        </div>
        {modo === 'real' ? <><p>O registro de despacho e a etiqueta da transportadora estarão disponíveis após a integração do serviço de entregas.</p><PedidosAdmin expedicao /></> : <>
            <h1>Despacho</h1>
            <p className="mensagem" role="status"><strong>Demonstração com dados fictícios.</strong> As ações abaixo simulam o fluxo e não alteram pedidos da loja.</p>
            <p>Pagamento aprovado → Nota autorizada → Separação e conferência → Despacho</p>
            <div className="despacho-resumo"><article><strong>{pendentes.length}</strong><span>Aguardando despacho</span></article><article><strong>{pendentes.filter((item) => etapas[item.id] === 'separado').length}</strong><span>Separados e conferidos</span></article></div>
            <div className="dashboard-tabela"><table><caption>Pedidos pagos, com nota emitida, aguardando despacho</caption><thead><tr><th>Pedido / cliente</th><th>Destino</th><th>Pagamento</th><th>Nota fiscal</th><th>Preparação</th><th>Ação</th></tr></thead><tbody>
                {pendentes.map((item) => <tr key={item.id}><td><strong>{item.id}</strong><br />{item.cliente}</td><td>{item.cidade}</td><td>Pago</td><td>NF-e {item.nota}<br />Autorizada</td><td>{nomesEtapas[etapas[item.id] || 'aguardando']}</td><td><button onClick={() => { setSelecionado(item.id); setAviso(''); }}>Conferir pedido</button></td></tr>)}
                {!pendentes.length && <tr><td colSpan={6}>Todos os pedidos de exemplo foram despachados.</td></tr>}
            </tbody></table></div>
            <section className="despacho-detalhe" aria-labelledby="despacho-pedido"><h2 id="despacho-pedido">Pedido {pedido.id}</h2><p>{pedido.cliente} · {pedido.cidade}</p><p>Pago · NF-e {pedido.nota} autorizada · {nomesEtapas[etapa]}</p>
                <h3>Itens para separar</h3><ul>{pedido.itens.map((item) => <li key={item.nome}><strong>{item.quantidade} ×</strong> {item.nome}</li>)}</ul>
                <div className="dashboard-acoes">
                    <button onClick={() => setAviso('No pedido real, esta ação baixa a DANFE autorizada para impressão. Não há documento fiscal nos dados de exemplo.')}>DANFE para impressão</button>
                    <button onClick={() => setAviso('A etiqueta de postagem será fornecida pelo serviço de entregas escolhido. No pedido real, a etiqueta de destinatário já pode ser impressa no detalhe.')}>Etiqueta de envio</button>
                    <button disabled={etapa !== 'aguardando'} onClick={() => { setEtapas({ ...etapas, [pedido.id]: 'separado' }); setAviso('Conferência simulada: itens separados e pacote pronto para envio.'); }}>Simular conferência</button>
                    <button disabled={etapa !== 'separado'} onClick={() => { setEtapas({ ...etapas, [pedido.id]: 'despachado' }); setAviso('Despacho simulado. O pedido saiu desta fila de exemplo; na integração futura, o serviço de entregas informará o rastreamento.'); }}>Simular despacho</button>
                </div>
                {aviso && <p role="status" className="mensagem">{aviso}</p>}
            </section>
            <button onClick={() => { setEtapas({}); setSelecionado(exemplos[0].id); setAviso(''); }}>Reiniciar demonstração</button>
        </>}
    </>;
}
