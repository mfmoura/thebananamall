import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../services/api';
import useDados from '../hooks/useDados';
import { MensagemErro } from '../components/Formulario';
import { dinheiro } from './Catalogo';

export default function Pedidos() {
    const { id } = useParams();
    const [revisao, setRevisao] = useState(0);
    const [falha, setFalha] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const { dados, erro, carregando } = useDados(() => id ? api.pedido(id) : api.pedidos(), `${id}-${revisao}`);
    const executar = async (acao) => {
        setOcupado(true); setFalha(null);
        try { await acao(); } catch (e) { setFalha(e); } finally { setOcupado(false); }
    };
    return <section className="tela"><h1>{id ? `Pedido #${id}` : 'Meus pedidos'}</h1><MensagemErro erro={erro || falha} />
        {carregando && <p role="status">Carregando pedidos…</p>}
        {dados && (id ? [dados] : dados).map((pedido) => <article className="produto-card" key={pedido.id}>
            <h2><Link to={`/pedidos/${pedido.id}`}>Pedido #{pedido.id}</Link></h2>
            <p>Status: {pedido.status}</p><p>Total: {dinheiro(pedido.total)}</p>
            <p>{pedido.processamento_fiscal?.mensagem || 'Seus dados estão sendo processados. A emissão fiscal depende da confirmação do pagamento.'}</p>
            {id && <><ul>{pedido.itens?.map((item) => <li key={item.id}><Link to={`/produtos/${item.codigo}`}>{item.produto?.nome || 'Produto indisponível'}</Link> · {Number(item.quantidade)} × {dinheiro(item.preco)}</li>)}</ul>
                <button disabled={ocupado} onClick={() => executar(async () => {
                    const blob = await api.danfe(id);
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url; link.download = `danfe-pedido-${id}.pdf`; link.click();
                    setTimeout(() => URL.revokeObjectURL(url), 1000);
                })}>Baixar DANFE</button>
                <button disabled={ocupado} onClick={() => { if (window.confirm('Cancelar este pedido?')) executar(async () => { await api.cancelarPedido(id); setRevisao((valor) => valor + 1); }); }}>Cancelar pedido</button>
                <button disabled={ocupado} onClick={() => setRevisao((valor) => valor + 1)}>Atualizar status</button>
            </>}
        </article>)}
        {Array.isArray(dados) && !dados.length && <p>Você ainda não fez pedidos.</p>}
    </section>;
}
