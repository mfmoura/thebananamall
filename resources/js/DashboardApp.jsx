import { marca } from './marca';
import { useEffect, useRef, useState } from 'react';
import { BrowserRouter, Link, NavLink, Navigate, Outlet, Route, Routes, useNavigate, useOutletContext } from 'react-router-dom';
import Formulario, { MensagemErro } from './components/Formulario';
import { sessao } from './services/api';
import PaginaSkeleton from './components/PaginaSkeleton';
import { useProcessamento } from './context/ProcessamentoContext';
import { prepararDashboard } from './services/dashboard';
import Produtos from './pages/dashboard/Produtos';
import Logistica, { ProdutoLogistica } from './pages/dashboard/Logistica';
import ProdutoFormulario from './pages/dashboard/ProdutoFormulario';
import Usuarios from './pages/dashboard/Usuarios';
import UsuarioFormulario from './pages/dashboard/UsuarioFormulario';
import Despacho from './pages/dashboard/Despacho';
import PedidosAdmin, { PedidoDetalhes } from './pages/dashboard/Pedidos';
import ConfirmacaoEmail from './pages/dashboard/ConfirmacaoEmail';

function Login() {
    const navigate = useNavigate();
    return <main className="dashboard-login"><section className="tela tela-form">
        <p className="dashboard-marca">{marca.nome} · Administração</p>
        <h1>Entrar no painel</h1>
        <p>Acesso exclusivo para administradores.</p>
        <Formulario bloquearTela limparSenhasAposEnvio={false} action="/dashboard/login" campos={[
            { nome: 'login', id: 'dashboard-username', label: 'Login ou e-mail', autoComplete: 'username' },
            { nome: 'password', id: 'dashboard-current-password', label: 'Senha', tipo: 'password', autoComplete: 'current-password' },
        ]} botao="Entrar" sucesso="" enviar={async (dados) => {
            await sessao('/dashboard/login', dados);
            navigate('/dashboard/', { replace: true });
        }} />
        <a href="/">Voltar para a loja</a>
    </section></main>;
}

function Layout() {
    const navigate = useNavigate();
    const { executar } = useProcessamento();
    const [usuario, setUsuario] = useState(null);
    const [erro, setErro] = useState(null);
    const preparacao = useRef(null);
    useEffect(() => {
        let ativo = true;
        preparacao.current ??= prepararDashboard();
        preparacao.current.then((conta) => { if (ativo) setUsuario(conta); }).catch((e) => {
            if (!ativo) return;
            if ([401, 403].includes(e.status)) navigate('/dashboard/login', { replace: true });
            else setErro(e);
        });
        const expirar = () => navigate('/dashboard/login', { replace: true });
        window.addEventListener('lcb:dashboard-expirado', expirar);
        return () => { ativo = false; window.removeEventListener('lcb:dashboard-expirado', expirar); };
    }, [navigate]);
    if (erro) return <main className="tela"><MensagemErro erro={erro} /><button onClick={() => window.location.reload()}>Tentar novamente</button></main>;
    if (!usuario) return <main className="pagina"><PaginaSkeleton rotulo="Verificando acesso" /></main>;
    return <div className="dashboard-layout">
        <aside className="dashboard-menu"><p className="dashboard-marca">{marca.nome}</p><p>Administração</p>
            <nav aria-label="Navegação administrativa"><NavLink to="/dashboard/" end><i className="fa-solid fa-gauge" aria-hidden="true" /> Visão geral</NavLink>
                {usuario.paginas?.some((pagina) => ['estoque', 'manutencao', 'contabilidade'].includes(pagina)) && <NavLink to="/dashboard/logistica"><i className="fa-solid fa-warehouse" aria-hidden="true" /> Logística</NavLink>}
                {usuario.paginas?.includes('estoque') && <NavLink to="/dashboard/produtos"><i className="fa-solid fa-box" aria-hidden="true" /> Produtos</NavLink>}
                {(usuario.paginas?.length > 0) && <NavLink to="/dashboard/usuarios"><i className="fa-solid fa-users" aria-hidden="true" /> Usuários</NavLink>}
                {usuario.paginas?.includes('pedidos') && <NavLink to="/dashboard/pedidos"><i className="fa-solid fa-receipt" aria-hidden="true" /> Pedidos</NavLink>}
                {usuario.paginas?.includes("pedidos") && <NavLink to="/dashboard/expedicao"><i className="fa-solid fa-truck" aria-hidden="true" /> Despacho</NavLink>}
            </nav>
            <a href="/">Ir para a loja</a>
        </aside>
        <div className="dashboard-conteudo"><header className="dashboard-cabecalho"><span>{usuario.login}</span>
            <button onClick={async () => {
                try { await executar(async () => { await sessao('/dashboard/logout'); navigate('/dashboard/login', { replace: true }); }, 'Saindo do painel'); }
                catch (e) { setErro(e); }
            }}>Sair do painel</button>
        </header><main className="tela"><Outlet context={usuario} /></main></div>
    </div>;
}

function Inicio() {
    const usuario = useOutletContext();
    return <><p className="dashboard-marca">Painel administrativo</p><h1>Visão geral</h1>
        <section className="dashboard-boas-vindas"><i className="fa-solid fa-shield-halved" aria-hidden="true" /><h2>Bem-vindo, {usuario.login}</h2><p>Este é o espaço de administração da loja {marca.nome}.</p>
            {usuario.paginas?.includes('estoque') && <Link to="/dashboard/produtos">Gerenciar produtos</Link>}
            {(usuario.paginas?.length > 0) && <p><Link to="/dashboard/usuarios">Gerenciar usuários</Link></p>}
        </section></>;
}

function PaginaAdministrativa({ pagina }) {
    const usuario = useOutletContext();
    return (Array.isArray(pagina) ? pagina.some((item) => usuario.paginas?.includes(item)) : usuario.paginas?.includes(pagina)) ? <Outlet context={usuario} /> : <p role="alert">Seu perfil não tem permissão para acessar esta página.</p>;
}

export default function DashboardApp() {
    return <BrowserRouter><Routes>
        <Route path="/dashboard/login" element={<Login />} />
        {['/dashboard/email/verify', '/dashboard/email/pendente', '/dashboard/senha-obrigatoria'].map((path) => <Route key={path} path={path} element={<ConfirmacaoEmail />} />)}
        <Route path="/dashboard" element={<Layout />}>
            <Route index element={<Inicio />} />
            <Route element={<PaginaAdministrativa pagina={['estoque', 'manutencao', 'contabilidade']} />}>
                <Route path="logistica" element={<Logistica />} />
                <Route path="logistica/:id" element={<ProdutoLogistica />} />
            </Route>
            <Route element={<PaginaAdministrativa pagina="estoque" />}>
                <Route path="produtos" element={<Produtos />} />
                <Route path="produtos/novo" element={<ProdutoFormulario key="novo" />} />
                <Route path="produtos/:id/editar" element={<ProdutoFormulario />} />
            </Route>
            <Route element={<PaginaAdministrativa pagina="pedidos" />}>
                <Route path="expedicao" element={<Despacho />} />
                <Route path="expedicao/:id" element={<PedidoDetalhes expedicao />} />
                <Route path="pedidos" element={<PedidosAdmin />} />
                <Route path="pedidos/:id" element={<PedidoDetalhes />} />
            </Route>
            <Route element={<PaginaAdministrativa pagina={['usuarios', 'manutencao', 'estoque', 'contabilidade', 'pedidos']} />}>
                <Route path="usuarios" element={<Usuarios />} />
                <Route path="usuarios/novo" element={<UsuarioFormulario key="novo" />} />
                <Route path="usuarios/:id/editar" element={<UsuarioFormulario />} /><Route path="usuarios/:id/visualizar" element={<UsuarioFormulario somenteLeitura />} />
            </Route>
        </Route>
        <Route path="*" element={<Navigate to="/dashboard/" replace />} />
    </Routes></BrowserRouter>;
}
