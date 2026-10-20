import { useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { MensagemErro } from '../../components/Formulario';
import TabelaSkeleton from '../../components/TabelaSkeleton';
import useDados from '../../hooks/useDados';
import { useProcessamento } from '../../context/ProcessamentoContext';
import { dashboardApi } from '../../services/dashboard';

const status = { aguardando_pagamento: 'Aguardando pagamento', pago: 'Pago', em_processamento: 'Em processamento', enviado: 'Enviado', entregue: 'Entregue', cancelado: 'Cancelado', falhou: 'Falhou' };
const moeda = (valor) => Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function PedidosAdmin({ expedicao = false }) {
    const [parametros, setParametros] = useSearchParams();
    const pagina = Math.max(1, Number(parametros.get('page')) || 1);
    const filtro = parametros.get('status') || '';
    const numero = parametros.get('pedido_id') || '';
    const query = new URLSearchParams({ page: pagina, per_page: 10, ...(expedicao ? { expedicao: 1 } : {}), ...(filtro ? { status: filtro } : {}), ...(numero ? { pedido_id: numero } : {}) });
    const { dados, erro, carregando } = useDados(() => dashboardApi.pedidos(query), query.toString());
    const navegar = (page) => setParametros({ ...Object.fromEntries(parametros), page });
    return <><h1>{expedicao ? "Aguardando despacho" : "Pedidos"}</h1>{expedicao && <p>Pedidos pagos, com nota fiscal autorizada e ainda não enviados.</p>}<form className="dashboard-busca" onSubmit={(e) => {
        e.preventDefault(); const form = new FormData(e.currentTarget);
        setParametros(Object.fromEntries([...form.entries()].filter(([, valor]) => valor)));
    }}><label htmlFor="numero-pedido">Número do pedido</label><input id="numero-pedido" name="pedido_id" type="number" min="1" defaultValue={numero} />
        <label htmlFor="status-pedido">Status</label><select id="status-pedido" name="status" defaultValue={filtro}><option value="">Todos</option>{Object.entries(status).filter(([valor]) => !expedicao || ["pago", "em_processamento"].includes(valor)).map(([valor, texto]) => <option key={valor} value={valor}>{texto}</option>)}</select><button>Buscar</button></form>
        <MensagemErro erro={erro} />{carregando && <TabelaSkeleton rotulo="pedidos" colunas={['Número', 'Cliente', 'Data', 'Status', 'Total', 'Ações']} />}
        {dados && <><div className="dashboard-tabela"><table><caption>{dados.total} pedido(s)</caption><thead><tr><th>Número</th><th>Cliente</th><th>Data</th><th>Status</th><th>Total</th><th>Ações</th></tr></thead><tbody>{dados.data.map((p) => <tr key={p.id}><td>#{p.id}</td><td>#{p.cliente_id}</td><td>{new Date(p.data).toLocaleString('pt-BR')}</td><td>{status[p.status]}</td><td>{moeda(p.total)}</td><td><Link to={`/dashboard/${expedicao ? "expedicao" : "pedidos"}/${p.id}`}>Consultar / ações</Link></td></tr>)}{!dados.data.length && <tr><td colSpan={6}>Nenhum pedido encontrado.</td></tr>}</tbody></table></div>
            <nav className="dashboard-paginacao" aria-label="Paginação de pedidos"><button disabled={pagina <= 1 || carregando} onClick={() => navegar(pagina - 1)}>Anterior</button><span>Página {dados.current_page} de {dados.last_page}</span><button disabled={pagina >= dados.last_page || carregando} onClick={() => navegar(pagina + 1)}>Próxima</button></nav></>}
    </>;
}

export function PedidoDetalhes({ expedicao = false }) {
    const { id } = useParams();
    const [revisao, setRevisao] = useState(0);
    const [falha, setFalha] = useState(null);
    const [etiqueta, setEtiqueta] = useState(null);
    const { executar } = useProcessamento();
    const { dados, erro, carregando } = useDados(() => dashboardApi.pedido(id), `${id}-${revisao}`);
    const acao = async (callback) => { setFalha(null); try { await executar(callback, 'Processando pedido'); } catch (e) { setFalha(e); } };
    const pedido = dados?.pedido;
    return <><Link to={expedicao ? "/dashboard/expedicao" : "/dashboard/pedidos"}>{expedicao ? "Voltar para expedição" : "Voltar para pedidos"}</Link><h1>Pedido #{id}</h1><MensagemErro erro={erro || falha} />{carregando && <TabelaSkeleton rotulo="itens do pedido" linhas={5} colunas={['Produto', 'Quantidade', 'Preço unitário']} />}
        {pedido && <><p>Cliente #{pedido.cliente_id} · {status[pedido.status]} · {new Date(pedido.data).toLocaleString('pt-BR')}</p><p>Pagamento: {pedido.forma_pagamento?.nome} · Total: {moeda(pedido.total)}</p><p>Produtos: {moeda(pedido.subtotal)} · Frete: {moeda(pedido.custo_frete)} {pedido.frete?.nome}</p>{pedido.frete?.prazo && <p>Prazo: {pedido.frete.prazo}</p>}<p>{pedido.processamento_fiscal?.mensagem}</p>
            <div className="dashboard-tabela"><table><thead><tr><th>Produto</th><th>Quantidade</th><th>Preço unitário</th></tr></thead><tbody>{pedido.itens.map((item) => <tr key={item.id}><td>{item.produto?.nome || `Produto #${item.codigo}`}{item.tamanho && ` · Tamanho ${item.tamanho}`}</td><td>{Number(item.quantidade)}</td><td>{moeda(item.preco)}</td></tr>)}</tbody></table></div>
            <div className="dashboard-acoes"><button disabled={!dados.pode_cancelar} onClick={() => {
                if (window.confirm(`Cancelar o pedido #${id}? O estoque será estornado e o histórico preservado.`)) acao(async () => { await dashboardApi.cancelarPedido(id); setRevisao((v) => v + 1); setEtiqueta(null); });
            }}>Cancelar pedido</button><button disabled={!dados.danfe_disponivel} onClick={() => acao(async () => {
                const blob = await dashboardApi.danfePedido(id); const url = URL.createObjectURL(blob);
                const link = document.createElement('a'); link.href = url; link.download = `danfe-pedido-${id}.pdf`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 60000);
            })}>Baixar DANFE para impressão</button><button disabled={!dados.etiqueta_disponivel} onClick={() => acao(async () => setEtiqueta({ ...await dashboardApi.etiquetaPedido(id), id }))}>Gerar etiqueta de destinatário</button></div>
            <p>O cancelamento está disponível somente enquanto não houver cobrança criada. A etiqueta usa o endereço registrado no pedido; não substitui etiqueta de postagem de transportadora.</p>
        </>}
        {etiqueta?.id === id && <section className="pedido-etiqueta"><h2>Etiqueta de destinatário</h2><p>Pedido #{etiqueta.pedido_id}</p><p>{etiqueta.nome}</p><p>{etiqueta.endereco.logradouro}, {etiqueta.endereco.numero} {etiqueta.endereco.complemento}</p><p>{etiqueta.endereco.bairro}</p><p>{etiqueta.endereco.cidade} / {etiqueta.endereco.estado}</p><p>CEP {etiqueta.endereco.cep} · {etiqueta.endereco.pais || 'BR'}</p><button onClick={() => window.print()}>Imprimir etiqueta</button></section>}
    </>;
}
