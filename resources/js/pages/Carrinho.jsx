import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useLoja } from '../context/LojaContext';
import { carrinhoApi } from '../services/carrinho';
import { dinheiro } from './Catalogo';
import { MensagemErro } from '../components/Formulario';
import ItemCarrinho from '../components/ItemCarrinho';
import AvisoReservaCarrinho from '../components/AvisoReservaCarrinho';

export default function Carrinho() {
    const { carrinho, executarCarrinho, carrinhoOcupado, usuario } = useLoja();
    const [erro, setErro] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const executar = async (acao) => {
        setOcupado(true); setErro(null);
        try { await executarCarrinho(acao); } catch (e) { setErro(e); } finally { setOcupado(false); }
    };
    const itens = carrinho.itens || [];
    const total = itens.reduce((soma, item) => soma + Math.round(Number(item.preco_atual ?? item.preco ?? 0) * 100 * item.quantidade), 0);
    return <section className="tela"><h1>Meu carrinho</h1><MensagemErro erro={erro} />
        <AvisoReservaCarrinho />
        {!itens.length ? <p>Seu carrinho está vazio. <Link to="/produtos">Conheça nossos produtos</Link>.</p> : <>
            {itens.map((item) => <ItemCarrinho key={`${item.codigo}:${item.tamanho ?? ''}`} item={item} />)}
            <h2>Total: {dinheiro(total / 100)}</h2>
            <p>Valores e disponibilidade são conferidos novamente ao finalizar.</p>
            <Link className="botao-principal" aria-disabled={carrinhoOcupado} to={usuario ? '/checkout' : '/login'} onClick={(event) => { if (carrinhoOcupado) { event.preventDefault(); return; } if (!usuario) sessionStorage.setItem('lcb.destino', '/checkout'); }}>{usuario ? 'Finalizar compra' : 'Entrar para finalizar'}</Link>
            <button disabled={ocupado || carrinhoOcupado} onClick={() => { if (window.confirm('Remover todos os itens do carrinho?')) executar(carrinhoApi.limpar); }}>Esvaziar carrinho</button>
        </>}
    </section>;
}
