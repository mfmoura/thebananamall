import { useState } from 'react';
import { Link, useOutletContext, useSearchParams } from 'react-router-dom';
import { MensagemErro } from '../../components/Formulario';
import BloqueioAutenticacao from '../../components/BloqueioAutenticacao';
import { useProcessamento } from '../../context/ProcessamentoContext';
import useDados from '../../hooks/useDados';
import { dashboardApi } from '../../services/dashboard';

export const tiposUsuario = { cliente: 'Cliente', administrador: 'Administrador', logistica: 'Logística' };
export const paginasUsuario = { contabilidade: 'Contabilidade', estoque: 'Estoque', manutencao: 'Manutenção', usuarios: 'Usuários' };
export const podeGerenciarUsuario = (usuario, conta) => (usuario.administrador?.paginas || []).every((pagina) => conta.paginas.includes(pagina));

export default function Usuarios() {
    const conta = useOutletContext();
    const [parametros, setParametros] = useSearchParams();
    const pagina = Math.max(1, Number(parametros.get('page')) || 1);
    const busca = parametros.get('busca') || '';
    const tipo = parametros.get('tipo') || '';
    const [revisao, setRevisao] = useState(0);
    const [falha, setFalha] = useState(null);
    const { executar } = useProcessamento();
    const { dados, erro, carregando } = useDados(() => dashboardApi.usuarios(new URLSearchParams({ page: pagina, per_page: 10, busca, ...(tipo ? { tipo } : {}) })), `${pagina}-${busca}-${tipo}-${revisao}`);
    const mudarPagina = (page) => setParametros({ busca, ...(tipo ? { tipo } : {}), page });
    return <>
        <div className="dashboard-titulo"><h1>Usuários</h1><Link className="botao-principal" to="/dashboard/usuarios/novo"><i className="fa-solid fa-user-plus" aria-hidden="true" /> Novo usuário</Link></div>
        <form className="dashboard-busca" onSubmit={(event) => {
            event.preventDefault(); const dadosForm = new FormData(event.currentTarget);
            setParametros({ busca: dadosForm.get('busca').trim(), ...(dadosForm.get('tipo') ? { tipo: dadosForm.get('tipo') } : {}), page: 1 });
        }}>
            <label htmlFor="busca-usuario">Buscar por login ou e-mail</label><input id="busca-usuario" name="busca" key={busca} defaultValue={busca} maxLength={255} />
            <label htmlFor="tipo-usuario">Perfil</label><select id="tipo-usuario" name="tipo" key={tipo} defaultValue={tipo}><option value="">Todos</option>{Object.entries(tiposUsuario).map(([valor, label]) => <option key={valor} value={valor}>{label}</option>)}</select><button type="submit">Buscar</button>
        </form>
        <MensagemErro erro={erro || falha} />
        {carregando && <BloqueioAutenticacao rotulo="Carregando usuários" />}
        {dados && <><div className="dashboard-tabela"><table><caption>{dados.total} usuário(s) cadastrado(s)</caption><thead><tr><th>Login</th><th>E-mail</th><th>Perfil</th><th>Confirmação</th><th>Ações</th></tr></thead><tbody>
            {dados.data.map((usuario) => <tr key={usuario.id}><td>{usuario.login}{usuario.id === conta.id && ' (você)'}</td><td>{usuario.email}</td><td>{Object.entries(tiposUsuario).filter(([perfil]) => usuario[perfil]).map(([, label]) => label).join(', ') || 'Sem perfil'}</td><td>{usuario.email_validado ? 'Confirmado' : 'Pendente'}</td><td><div className="dashboard-acoes"><Link to={`/dashboard/usuarios/${usuario.id}/editar`}>Detalhes / editar</Link><button disabled={usuario.id === conta.id || !podeGerenciarUsuario(usuario, conta)} onClick={async () => {
                if (!window.confirm(`Excluir a conta “${usuario.login}”? O acesso será revogado e o histórico será preservado.`)) return;
                setFalha(null);
                try { await executar(async () => {
                    await dashboardApi.excluirUsuario(usuario.id);
                    if (dados.data.length === 1 && pagina > 1) mudarPagina(pagina - 1);
                    else setRevisao((valor) => valor + 1);
                }, 'Excluindo usuário'); } catch (e) { setFalha(e); }
            }}>Excluir</button></div></td></tr>)}
            {!dados.data.length && <tr><td colSpan={5}>Nenhum usuário encontrado.</td></tr>}
        </tbody></table></div><nav className="dashboard-paginacao" aria-label="Paginação de usuários"><button disabled={pagina <= 1 || carregando} onClick={() => mudarPagina(pagina - 1)}>Anterior</button><span>Página {dados.current_page} de {dados.last_page}</span><button disabled={pagina >= dados.last_page || carregando} onClick={() => mudarPagina(pagina + 1)}>Próxima</button></nav></>}
    </>;
}
