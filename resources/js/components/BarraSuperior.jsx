import { useRef } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { retornoAposLogout } from '../services/navegacao';
import { useLoja } from '../context/LojaContext';
import { useCarrinhoResumo, useMenus } from '../hooks/loja';
import ItemCarrinho from './ItemCarrinho';
import AvisoReservaCarrinho from './AvisoReservaCarrinho';
const moeda = (valor) => (valor / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
export default function BarraSuperior() {
 const { usuario, logout, setErro, carrinhoOcupado } = useLoja();
 const navigate = useNavigate();
 const location = useLocation();
 const nav = useRef(null);
 const abrir = useMenus(nav);
 const { itens, total, quantidade } = useCarrinhoResumo();
 return (
<header className="barra-superior">
    <nav className="barra-conteudo" aria-label="Navegação principal" ref={nav}>
        <Link className="marca" to="/" aria-label="LCB — página inicial">
            <img src="/images/lcb-logo.png" alt="Liga Comunista Brasileira" width="1920" height="720" />
        </Link>
        <div className="barra-acoes">
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
                    <button className="botao-principal" type="button" disabled={!itens.length || carrinhoOcupado} onClick={() => { if (!usuario) sessionStorage.setItem('lcb.destino', '/checkout'); navigate(usuario ? '/checkout' : '/login'); }}><i className="fa-solid fa-lock" aria-hidden="true" />Finalizar compra</button>
                    <p className="menu-observacao">Entre como Cliente para finalizar a compra.</p>
                </div>
            </details>
        </div>
    </nav>
</header>

);
}
