import { useEffect } from 'react';
import { BrowserRouter, Link, Navigate, Outlet, Route, Routes, useLocation } from 'react-router-dom';
import { LojaProvider, useLoja } from './context/LojaContext';
import BarraSuperior from './components/BarraSuperior';
import Rodape from './components/Rodape';
import Catalogo from './pages/Catalogo';
import Carrinho from './pages/Carrinho';
import Checkout from './pages/Checkout';
import Conta from './pages/Conta';
import Pedidos from './pages/Pedidos';
import { Callback, Cadastro, FluxoEmailSenha, Login } from './pages/Autenticacao';
import { MensagemErro } from './components/Formulario';

function Protegida() {
    const { usuario, carregando } = useLoja();
    const location = useLocation();
    useEffect(() => {
        if (!usuario && !carregando) sessionStorage.setItem('lcb.destino', location.pathname);
    }, [usuario, carregando, location.pathname]);
    if (carregando) return <p role="status">Carregando conta…</p>;
    return usuario ? <Outlet /> : <Navigate to="/login" replace />;
}

function Conteudo() {
    const { erro, setErro } = useLoja();
    const location = useLocation();
    useEffect(() => {
        window.scrollTo(0, 0);
        document.querySelectorAll('details[open]').forEach((menu) => { menu.open = false; });
    }, [location.pathname]);
    return <>
        <a className="skip-link" href="#conteudo">Pular para o conteúdo</a>
        <BarraSuperior />
        <main id="conteudo" className="pagina" tabIndex={-1}>
            {erro && <><MensagemErro erro={erro} /><button onClick={() => setErro('')}>Fechar aviso</button></>}
            <Routes>
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
                    <Route path="/pedidos" element={<Pedidos />} />
                    <Route path="/pedidos/:id" element={<Pedidos />} />
                </Route>
                <Route path="*" element={<section className="tela"><h1>Página não encontrada</h1><Link to="/produtos">Ir para a loja</Link></section>} />
            </Routes>
        </main>
        <Rodape />
    </>;
}

export default function LojaApp() {
    return <BrowserRouter><LojaProvider><Conteudo /></LojaProvider></BrowserRouter>;
}
