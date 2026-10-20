import { useEffect, useRef, useState } from 'react';
import { Link, useOutletContext, useParams, useSearchParams } from 'react-router-dom';
import Formulario, { MensagemErro } from '../../components/Formulario';
import TabelaSkeleton from '../../components/TabelaSkeleton';
import useDados from '../../hooks/useDados';
import { dashboardApi } from '../../services/dashboard';

function Paginacao({ dados, carregando, mudar }) {
    return dados && <nav className="dashboard-paginacao" aria-label="Paginação"><button disabled={carregando || dados.current_page <= 1} onClick={() => mudar(dados.current_page - 1)}>Anterior</button><span>Página {dados.current_page} de {dados.last_page}</span><button disabled={carregando || dados.current_page >= dados.last_page} onClick={() => mudar(dados.current_page + 1)}>Próxima</button></nav>;
}

export default function Logistica() {
    const [parametros, setParametros] = useSearchParams();
    const busca = parametros.get('busca') || '';
    const pagina = Math.max(1, Number(parametros.get('page')) || 1);
    const { dados, erro, carregando } = useDados(() => dashboardApi.produtosLogistica(new URLSearchParams({ busca, page: pagina, per_page: 10 })), `${busca}-${pagina}`);
    return <><h1>Logística</h1><p>Selecione um produto para consultar o histórico e registrar movimentações de estoque.</p>
        <form className="dashboard-busca" onSubmit={(event) => { event.preventDefault(); setParametros({ busca: new FormData(event.currentTarget).get('busca').trim(), page: 1 }); }}>
            <label htmlFor="busca-logistica">Buscar por nome ou código</label><div><input id="busca-logistica" name="busca" defaultValue={busca} maxLength={255} /><button>Buscar</button></div>
        </form><MensagemErro erro={erro} />{carregando && <TabelaSkeleton rotulo="produtos de logística" colunas={['Código', 'Produto', 'Status', 'Ações']} />}
        {dados && <div className="dashboard-tabela"><table><caption>{dados.total} produto(s)</caption><thead><tr><th>Código</th><th>Produto</th><th>Status</th><th>Ações</th></tr></thead><tbody>
            {dados.data.map((produto) => <tr key={produto.id}><td>{produto.codigo}</td><td><Link to={`/dashboard/logistica/${produto.id}`}>{produto.nome}</Link></td><td>{produto.ativo ? 'Ativo' : 'Inativo'}</td><td><Link to={`/dashboard/logistica/${produto.id}`}>Histórico e ajustes</Link></td></tr>)}
            {!dados.data.length && <tr><td colSpan={4}>Nenhum produto encontrado.</td></tr>}
        </tbody></table></div>}
        <Paginacao dados={dados} carregando={carregando} mudar={(page) => setParametros({ busca, page })} />
    </>;
}

function ModalAjuste({ fechar, children }) {
    const dialogo = useRef(null);
    useEffect(() => {
        const elemento = dialogo.current;
        elemento.showModal();
        return () => elemento.close();
    }, []);
    return <dialog ref={dialogo} className="logistica-modal" aria-labelledby="titulo-ajuste-logistica" onCancel={(event) => { event.preventDefault(); fechar(); }}>
        <button className="logistica-modal-fechar" onClick={fechar} aria-label="Fechar ajuste de estoque"><i className="fa-solid fa-xmark" aria-hidden="true" /> Fechar</button>
        {children}
    </dialog>;
}

export function ProdutoLogistica() {
    const { id } = useParams();
    return <DetalheProdutoLogistica key={id} id={id} />;
}

function DetalheProdutoLogistica({ id }) {
    const usuario = useOutletContext();
    const podeAjustar = usuario.paginas?.some((pagina) => ['estoque', 'manutencao'].includes(pagina));
    const podeNota = usuario.paginas?.includes('contabilidade');
    const [tipo, setTipo] = useState(podeAjustar ? 'ajuste' : 'nota');
    const [revisao, setRevisao] = useState(0);
    const [pagina, setPagina] = useState(1);
    const [formularioAberto, setFormularioAberto] = useState(false);
    const [mensagem, setMensagem] = useState('');
    const produto = useDados(() => dashboardApi.produtoLogistica(id), `${id}-${revisao}`);
    const historico = useDados(() => dashboardApi.historicoEstoque(new URLSearchParams({ produto_id: id, page: pagina, per_page: 10 })), `${id}-${pagina}-${revisao}`);
    return <><Link to="/dashboard/logistica">Voltar para logística</Link><h1>{produto.dados?.nome || 'Estoque do produto'}</h1>
        <MensagemErro erro={produto.erro || historico.erro} />
        {mensagem && <p className="mensagem" role="status">{mensagem}</p>}
        {produto.carregando && <TabelaSkeleton rotulo="estoque por tamanho" linhas={4} colunas={['Tamanho', 'Contado', 'Reservado', 'Disponível']} />}
        {produto.dados && <>
            <p>Código: {produto.dados.codigo} · Estoque contado: {produto.dados.estoque_contado ?? 0} · Disponível: {produto.dados.estoque ?? 0}</p>
            {produto.dados.grade?.length > 0 && <div className="dashboard-tabela"><table><caption>Estoque por tamanho</caption><thead><tr><th>Tamanho</th><th>Contado</th><th>Reservado</th><th>Disponível</th></tr></thead><tbody>{produto.dados.estoque_por_tamanho?.map((item) => <tr key={item.tamanho}><td>{item.tamanho}</td><td>{item.estoque_contado}</td><td>{item.estoque_reservado}</td><td>{item.estoque}</td></tr>)}</tbody></table></div>}
            <button className="botao-principal" onClick={() => setFormularioAberto(true)} aria-haspopup="dialog">Cadastrar movimentação</button>
            {formularioAberto && <ModalAjuste fechar={() => setFormularioAberto(false)}><section className="tela-form"><h2 id="titulo-ajuste-logistica">Registrar movimentação</h2><p>{produto.dados.nome}</p>
                {podeAjustar && podeNota && <label>Tipo de registro <select value={tipo} onChange={(event) => setTipo(event.target.value)}><option value="ajuste">Ajuste de Estoque</option><option value="nota">Entrada com nota</option></select></label>}
                <p>{tipo === 'nota' ? 'Informe a referência da nota. A verificação em outro sistema será implementada posteriormente.' : 'Entradas e saídas serão registradas com o status Ajuste de Estoque.'}</p>
                <Formulario key={`${id}-${tipo}`} bloquearTela botao="Registrar movimentação" sucesso="" campos={[
                    { nome: 'quantidade', label: 'Quantidade', tipo: 'number', min: produto.dados.grade?.length ? '1' : '0.001', step: produto.dados.grade?.length ? '1' : '0.001' },
                    { nome: 'nota', label: 'Referência da nota (verificação futura)', maxLength: 120, oculto: tipo !== 'nota' },
                    { nome: 'observacao', label: 'Observação', obrigatorio: false, maxLength: 2000 },
                ]} enviar={async (dados) => {
                    setMensagem('');
                    await dashboardApi.movimentarEstoque({ ...dados, produto_id: Number(id), tipo, direcao: tipo === 'nota' ? 'entrada' : dados.direcao });
                    setMensagem('Movimentação registrada.');
                    setFormularioAberto(false);
                    setPagina(1); setRevisao((valor) => valor + 1);
                }}>
                    <label className={!produto.dados.grade?.length ? 'campo-reservado' : undefined} inert={!produto.dados.grade?.length || undefined} aria-hidden={!produto.dados.grade?.length || undefined}>Tamanho<select name="tamanho" required={Boolean(produto.dados.grade?.length)} disabled={!produto.dados.grade?.length}><option value="">Selecione</option>{produto.dados.grade?.map((tamanho) => <option key={tamanho} value={tamanho}>{tamanho}</option>)}</select></label>
                    <label className={tipo !== 'ajuste' ? 'campo-reservado' : undefined} inert={tipo !== 'ajuste' || undefined} aria-hidden={tipo !== 'ajuste' || undefined}>Operação<select name="direcao" required={tipo === 'ajuste'} disabled={tipo !== 'ajuste'}><option value="entrada">Adicionar estoque</option><option value="saida">Remover estoque</option></select></label>
                </Formulario>
            </section></ModalAjuste>}
        </>}
        <h2>Histórico de movimentações</h2>
        {historico.carregando && <TabelaSkeleton rotulo="movimentações de estoque" colunas={['Data', 'Operação', 'Quantidade', 'Status', 'Nota / referência', 'Observação']} />}
        {historico.dados && <div className="dashboard-tabela"><table><caption>{historico.dados.total} movimentação(ões)</caption><thead><tr><th>Data</th><th>Operação</th><th>Quantidade</th><th>Status</th><th>Nota / referência</th><th>Observação</th></tr></thead><tbody>
            {historico.dados.data.map((movimento) => <tr key={movimento.id}><td>{new Date(movimento.data).toLocaleString('pt-BR')}</td><td>{movimento.direcao === 'entrada' ? 'Entrada' : 'Saída'}</td><td>{movimento.tamanho && <span>Tamanho {movimento.tamanho} · </span>}{Number(movimento.quantidade).toLocaleString('pt-BR', { maximumFractionDigits: 3 })}</td><td>{movimento.nota?.status || (movimento.estorno_de_id ? 'Estorno' : 'Movimentação')}</td><td>{movimento.nota?.referencia || '—'}</td><td>{movimento.nota?.observacao || '—'}</td></tr>)}
            {!historico.dados.data.length && <tr><td colSpan={6}>Nenhuma movimentação registrada para este produto.</td></tr>}
        </tbody></table></div>}
        <Paginacao dados={historico.dados} carregando={historico.carregando} mudar={setPagina} />
    </>;
}
