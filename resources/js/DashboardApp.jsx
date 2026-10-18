import { useEffect, useRef, useState } from 'react';
import { BrowserRouter, Link, NavLink, Navigate, Outlet, Route, Routes, useNavigate, useOutletContext } from 'react-router-dom';
import Formulario, { MensagemErro } from './components/Formulario';
import { sessao } from './services/api';
import BloqueioAutenticacao from './components/BloqueioAutenticacao';
import { useProcessamento } from './context/ProcessamentoContext';
import { prepararDashboard } from './services/dashboard';
import Produtos from './pages/dashboard/Produtos';
import ProdutoFormulario from './pages/dashboard/ProdutoFormulario';
import Usuarios from './pages/dashboard/Usuarios';
import UsuarioFormulario from './pages/dashboard/UsuarioFormulario';

function Login() {
    const navigate = useNavigate();
    return <main className="dashboard-login"><section className="tela tela-form">
        <p className="dashboard-marca">LCB · Administração</p>
        <h1>Entrar no painel</h1>
        <p>Acesso exclusivo para administradores.</p>
        <Formulario bloquearTela campos={[
            { nome: 'login', label: 'Login ou e-mail', autoComplete: 'username' },
            { nome: 'password', label: 'Senha', tipo: 'password', autoComplete: 'current-password' },
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
    if (!usuario) return <><main className="tela" role="status">Verificando acesso…</main><BloqueioAutenticacao rotulo="Verificando acesso" /></>;
    return <div className="dashboard-layout">
        <aside className="dashboard-menu"><p className="dashboard-marca">LCB</p><p>Administração</p>
            <nav aria-label="Navegação administrativa"><NavLink to="/dashboard/" end><i className="fa-solid fa-gauge" aria-hidden="true" /> Visão geral</NavLink>
                {usuario.paginas?.includes('estoque') && <NavLink to="/dashboard/produtos"><i className="fa-solid fa-box" aria-hidden="true" /> Produtos</NavLink>}
                {usuario.paginas?.includes('usuarios') && <NavLink to="/dashboard/usuarios"><i className="fa-solid fa-users" aria-hidden="true" /> Usuários</NavLink>}
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
        <section className="dashboard-boas-vindas"><i className="fa-solid fa-shield-halved" aria-hidden="true" /><h2>Bem-vindo, {usuario.login}</h2><p>Este é o espaço de administração da loja LCB.</p>
            {usuario.paginas?.includes('estoque') && <Link to="/dashboard/produtos">Gerenciar produtos</Link>}
            {usuario.paginas?.includes('usuarios') && <p><Link to="/dashboard/usuarios">Gerenciar usuários</Link></p>}
        </section></>;
}

function PaginaAdministrativa({ pagina }) {
    const usuario = useOutletContext();
    return usuario.paginas?.includes(pagina) ? <Outlet context={usuario} /> : <p role="alert">Seu perfil não tem permissão para acessar esta página.</p>;
}

export default function DashboardApp() {
    return <BrowserRouter><Routes>
        <Route path="/dashboard/login" element={<Login />} />
        <Route path="/dashboard" element={<Layout />}>
            <Route index element={<Inicio />} />
            <Route element={<PaginaAdministrativa pagina="estoque" />}>
                <Route path="produtos" element={<Produtos />} />
                <Route path="produtos/novo" element={<ProdutoFormulario key="novo" />} />
                <Route path="produtos/:id/editar" element={<ProdutoFormulario />} />
            </Route>
            <Route element={<PaginaAdministrativa pagina="usuarios" />}>
                <Route path="usuarios" element={<Usuarios />} />
                <Route path="usuarios/novo" element={<UsuarioFormulario key="novo" />} />
                <Route path="usuarios/:id/editar" element={<UsuarioFormulario />} />
            </Route>
        </Route>
        <Route path="*" element={<Navigate to="/dashboard/" replace />} />
    </Routes></BrowserRouter>;
}
