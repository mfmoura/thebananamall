import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { MensagemErro } from '../../components/Formulario';
import BloqueioAutenticacao from '../../components/BloqueioAutenticacao';
import { useProcessamento } from '../../context/ProcessamentoContext';
import useDados from '../../hooks/useDados';
import { dashboardApi } from '../../services/dashboard';

export default function Produtos() {
    const [parametros, setParametros] = useSearchParams();
    const pagina = Math.max(1, Number(parametros.get('page')) || 1);
    const busca = parametros.get('busca') || '';
    const [revisao, setRevisao] = useState(0);
    const [falha, setFalha] = useState(null);
    const { executar } = useProcessamento();
    const { dados, erro, carregando } = useDados(() => dashboardApi.listar(new URLSearchParams({ page: pagina, per_page: 10, busca })), `${pagina}-${busca}-${revisao}`);
    const mudarPagina = (page) => setParametros({ ...(busca ? { busca } : {}), page });
    return <>
        <div className="dashboard-titulo"><h1>Produtos</h1><Link className="botao-principal" to="/dashboard/produtos/novo"><i className="fa-solid fa-plus" aria-hidden="true" /> Novo produto</Link></div>
        <form className="dashboard-busca" onSubmit={(event) => {
            event.preventDefault();
            setParametros({ busca: new FormData(event.currentTarget).get('busca').trim(), page: 1 });
        }}><label htmlFor="busca-produto">Buscar por nome ou código</label><div><input id="busca-produto" key={busca} name="busca" defaultValue={busca} maxLength={255} /><button type="submit">Buscar</button></div></form>
        <MensagemErro erro={erro || falha} />
        {carregando && <BloqueioAutenticacao rotulo="Carregando produtos" />}
        {dados && <><div className="dashboard-tabela"><table><caption>{dados.total} produto(s) cadastrado(s)</caption><thead><tr><th>Foto</th><th>Código</th><th>Nome</th><th>Categoria</th><th>Preço</th><th>Status</th><th>Ações</th></tr></thead><tbody>
            {dados.data.map((produto) => {
                const foto = produto.imagens?.find((item) => item.principal) || produto.imagens?.[0];
                return <tr key={produto.id}><td>{foto ? <img src={foto.path} alt="" width="48" height="48" /> : '—'}</td><td>{produto.codigo}</td><td>{produto.nome}</td><td>{produto.categoria_cadastro?.nome || produto.categoria}</td><td>{produto.preco == null ? 'Sem preço' : Number(produto.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</td><td>{produto.ativo ? 'Ativo' : 'Inativo'}</td><td><div className="dashboard-acoes"><Link to={`/dashboard/produtos/${produto.id}/editar`}>Editar</Link><button onClick={async () => {
                    if (!window.confirm(`Excluir o produto “${produto.nome}”?`)) return;
                    setFalha(null);
                    try { await executar(async () => {
                        await dashboardApi.excluir(produto.id);
                        if (dados.data.length === 1 && pagina > 1) mudarPagina(pagina - 1);
                        else setRevisao((valor) => valor + 1);
                    }, 'Excluindo produto'); } catch (e) { setFalha(e); }
                }}>Excluir</button></div></td></tr>;
            })}
            {!dados.data.length && <tr><td colSpan={7}>Nenhum produto encontrado.</td></tr>}
        </tbody></table></div><nav className="dashboard-paginacao" aria-label="Paginação de produtos"><button disabled={pagina <= 1 || carregando} onClick={() => mudarPagina(pagina - 1)}>Anterior</button><span>Página {dados.current_page} de {dados.last_page}</span><button disabled={pagina >= dados.last_page || carregando} onClick={() => mudarPagina(pagina + 1)}>Próxima</button></nav></>}
    </>;
}
