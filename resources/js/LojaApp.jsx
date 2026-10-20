import { lazy, Suspense, useEffect } from 'react';
import { BrowserRouter, Link, Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { LojaProvider, useLoja } from './context/LojaContext';
import BarraSuperior from './components/BarraSuperior';
import Rodape from './components/Rodape';
import { Callback, Cadastro, FluxoEmailSenha, Login } from './pages/Autenticacao';
import { MensagemErro } from './components/Formulario';
import BloqueioAutenticacao from './components/BloqueioAutenticacao';
import PaginaSkeleton from './components/PaginaSkeleton';
import { destinoSeguro } from './services/navegacao';

const Catalogo = lazy(() => import('./pages/Catalogo'));
const Carrinho = lazy(() => import('./pages/Carrinho'));
const Checkout = lazy(() => import('./pages/Checkout'));
const Conta = lazy(() => import('./pages/Conta'));
const Pedidos = lazy(() => import('./pages/Pedidos'));
const Pagamento = lazy(() => import('./pages/Pagamento'));

function Protegida() {
    const { usuario, carregando } = useLoja();
    const location = useLocation();
    useEffect(() => {
        if (!usuario && !carregando) sessionStorage.setItem('lcb.destino', `${location.pathname}${location.search}${location.hash}`);
    }, [usuario, carregando, location.pathname, location.search, location.hash]);
    if (carregando) return <PaginaSkeleton rotulo="Verificando acesso" />;
    return usuario ? <Outlet /> : <Navigate to="/login" replace />;
}

function Conteudo() {
    const { erro, setErro, processandoAutenticacao, carrinhoOcupado } = useLoja();
    const location = useLocation();
    useEffect(() => {
        window.scrollTo(0, 0);
        document.querySelectorAll('details[open]').forEach((menu) => { menu.open = false; });
        const atual = destinoSeguro(`${location.pathname}${location.search}${location.hash}`);
        if (atual) sessionStorage.setItem('lcb.ultimaPagina', atual);
    }, [location.pathname, location.search, location.hash]);
    return <>
        <a className="skip-link" href="#conteudo">Pular para o conteúdo</a>
        <BarraSuperior />
        <main id="conteudo" className="pagina" tabIndex={-1}>
            {erro && <><MensagemErro erro={erro} /><button onClick={() => setErro('')}>Fechar aviso</button></>}
            <Suspense fallback={<PaginaSkeleton />}><Routes>
                <Route path="/" element={<Catalogo />} />
                <Route path="/produtos" element={<Catalogo />} />
                <Route path="/produtos/:id" element={<Catalogo />} />
                <Route path="/carrinho" element={<Carrinho />} />
                <Route path="/login" element={<Login />} />
                <Route path="/cadastro" element={<Cadastro />} />
                <Route path="/oauth/callback" element={<Callback />} />
                {['/email/verify', '/email/pendente', '/senha/recuperar', '/senha/redefinir', '/password/reset', '/senha-obrigatoria'].map((path) => <Route key={path} path={path} element={<FluxoEmailSenha />} />)}
                <Route element={<Protegida />}>
                    <Route path="/conta" element={<Conta />} />
                    <Route path="/checkout" element={<Checkout />} />
                    <Route path="/checkout/pagamento/:id" element={<Pagamento />} />
                    <Route path="/pedidos" element={<Pedidos />} />
                    <Route path="/pedidos/:id" element={<Pedidos />} />
                </Route>
                <Route path="*" element={<section className="tela"><h1>Página não encontrada</h1><Link to="/produtos">Ir para a loja</Link></section>} />
            </Routes></Suspense>
        </main>
        <Rodape />
        {processandoAutenticacao && <BloqueioAutenticacao />}
        {carrinhoOcupado && <BloqueioAutenticacao rotulo="Atualizando compra" />}
    </>;
}

export default function LojaApp() {
    return <BrowserRouter><LojaProvider><Conteudo /></LojaProvider></BrowserRouter>;
}
