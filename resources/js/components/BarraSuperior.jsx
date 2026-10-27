import { marca } from '../marca';
import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { retornoAposLogout } from '../services/navegacao';
import { useLoja } from '../context/LojaContext';
import { useCarrinhoResumo, useMenus } from '../hooks/loja';
import ItemCarrinho from './ItemCarrinho';
import AvisoReservaCarrinho from './AvisoReservaCarrinho';
const moeda = (valor) => (valor / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
function AcoesBarra() {
 const { usuario, logout, setErro, carrinhoOcupado } = useLoja();
 const navigate = useNavigate();
 const location = useLocation();
 const nav = useRef(null);
 const abrir = useMenus(nav);
 const { itens, total, quantidade } = useCarrinhoResumo();
 return (
        <div className="barra-acoes" ref={nav}>
            <Link to="/produtos" className="menu-catalogo">Loja</Link>
            <details className="menu-suspenso" data-menu="usuario" onToggle={abrir}>
                <summary><i className="fa-regular fa-user" aria-hidden="true" /><span>Minha conta</span><i className="fa-solid fa-chevron-down seta" aria-hidden="true" /></summary>
                <div className="menu-painel">
                    <p className="menu-titulo">Minha conta</p>
                    {!usuario && <Link className="menu-opcao" to="/login"><i className="fa-solid fa-arrow-right-to-bracket" aria-hidden="true" />Login</Link>}
                    {!usuario && <Link className="menu-opcao" to="/cadastro"><i className="fa-solid fa-user-plus" aria-hidden="true" />Criar conta</Link>}
                    {usuario && <Link className="menu-opcao" to="/conta"><i className="fa-regular fa-address-card" aria-hidden="true" />Informações do usuário</Link>}
                    {usuario?.cliente && <Link className="menu-opcao" to="/pedidos"><i className="fa-solid fa-box" aria-hidden="true" />Meus pedidos</Link>}
                    {usuario && <button className="menu-opcao" type="button" onClick={async () => { try { await logout(); navigate(retornoAposLogout(location), { replace: true }); } catch (e) { setErro(e.message); } }}><i className="fa-solid fa-arrow-right-from-bracket" aria-hidden="true" />Logout</button>}
                    <p className="menu-observacao">{usuario ? usuario.login : 'Você ainda não entrou.'}</p>
                </div>
            </details>
            <details className="menu-suspenso" data-menu="carrinho" onToggle={abrir}>
                <summary><span className="icone-carrinho"><i className="fa-solid fa-cart-shopping" aria-hidden="true" /><span className="carrinho-contador" data-carrinho-contador aria-label="Quantidade no carrinho">{quantidade.toLocaleString('pt-BR')}</span></span><span className="carrinho-resumo">Carrinho <strong data-carrinho-total>{moeda(total)}</strong></span><i className="fa-solid fa-chevron-down seta" aria-hidden="true" /></summary>
                <div className="menu-painel painel-carrinho">
                    <p className="menu-titulo">Meu carrinho</p>
                    <AvisoReservaCarrinho />
                    <div className="carrinho-itens" data-carrinho-itens aria-busy={carrinhoOcupado}>{itens.length ? itens.map((item) => <ItemCarrinho key={item.codigo} item={item} />) : <p className="carrinho-vazio">Seu carrinho está vazio.</p>}</div>
                    <div className="carrinho-total"><span>Total</span><strong data-carrinho-total>{moeda(total)}</strong></div>
                    <Link className="menu-opcao" to="/carrinho">Ver e editar carrinho</Link>
                    <button className="botao-principal" type="button" data-mp-checkout-cta="checkout-api" disabled={!itens.length || carrinhoOcupado} onClick={() => { if (!usuario) sessionStorage.setItem('lcb.destino', '/checkout'); navigate(usuario ? '/checkout' : '/login'); }}><i className="fa-solid fa-lock" aria-hidden="true" />Finalizar compra</button>
                    <p className="menu-observacao">Entre como Cliente para finalizar a compra.</p>
                </div>
            </details>
        </div>

);
}

export default function BarraSuperior() {
 const lateral = useRef(null);
 const [menuAberto, setMenuAberto] = useState(false);
 const location = useLocation();
 const fechar = () => lateral.current?.close();
 useEffect(() => { fechar(); }, [location.pathname, location.search, location.hash]);
 useEffect(() => {
    const desktop = window.matchMedia('(min-width: 768px)');
    const aoRedimensionar = () => { if (desktop.matches) fechar(); };
    desktop.addEventListener('change', aoRedimensionar);
    return () => desktop.removeEventListener('change', aoRedimensionar);
 }, []);
 useEffect(() => {
    if (!menuAberto) return;
    const anterior = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = anterior; };
 }, [menuAberto]);
 return <header className="barra-superior">
    <nav className="barra-conteudo" aria-label="Navegação principal">
        <Link className="marca" to="/" aria-label={`${marca.nome} — página inicial`}>
            <img src="/images/the-banana-mall-square.png" alt={marca.nome} width="1254" height="1254" />
        </Link>
        <div className="menu-desktop"><AcoesBarra /></div>
        <button className="menu-mobile-botao" type="button" aria-label="Abrir menu" aria-expanded={menuAberto} aria-controls="menu-lateral" aria-haspopup="dialog" onClick={() => { lateral.current.showModal(); setMenuAberto(true); }}>
            <i className="fa-solid fa-bars" aria-hidden="true" />
        </button>
    </nav>
    <dialog id="menu-lateral" className="menu-lateral" ref={lateral} aria-label="Menu da loja" onClose={() => setMenuAberto(false)} onClick={(event) => {
        if (event.target !== event.currentTarget) return;
        const rect = event.currentTarget.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) fechar();
    }}>
        <div className="menu-lateral-cabecalho"><strong>Menu da loja</strong><button type="button" aria-label="Fechar menu" onClick={fechar} autoFocus><i className="fa-solid fa-xmark" aria-hidden="true" /></button></div>
        <nav aria-label="Navegação mobile" onClick={(event) => { if (event.target.closest('a')) fechar(); }}><AcoesBarra /></nav>
    </dialog>
 </header>;
}