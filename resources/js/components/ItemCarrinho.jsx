import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useLoja } from '../context/LojaContext';
import { carrinhoApi } from '../services/carrinho';
import ControleQuantidade from './ControleQuantidade';
import { MensagemErro } from './Formulario';

const moeda = (valor) => Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export default function ItemCarrinho({ item }) {
    const { carrinhoOcupado, executarCarrinho } = useLoja();
    const [erro, setErro] = useState(null);
    const nome = (item.nome || 'Produto indisponível') + (item.tamanho ? ` - Tamanho ${item.tamanho}` : '');
    const preco = Number(item.preco_atual ?? item.preco ?? 0);
    const alterar = async (quantidade) => {
        setErro(null);
        try { await executarCarrinho(() => carrinhoApi.alterar(item.codigo, quantidade, item.tamanho)); }
        catch (e) { setErro(e); throw e; }
    };
    return <article className="item-carrinho-editavel">
        {item.imagem_principal && <Link className="item-carrinho-imagem" to={`/produtos/${item.codigo}`} tabIndex={-1} aria-hidden="true"><img src={item.imagem_principal} alt="" width="72" height="72" loading="lazy" decoding="async" /></Link>}
        <div className="item-carrinho-conteudo">
        <div className="item-carrinho-titulo"><Link to={`/produtos/${item.codigo}`}>{nome}</Link><strong>{moeda(Math.round(preco * item.quantidade * 100) / 100)}</strong></div>
        <p className="item-carrinho-preco">{moeda(preco)} por unidade</p>
        <div className="item-carrinho-acoes">
            <ControleQuantidade valor={item.quantidade} alterar={alterar} inteiro={item.tamanho != null} desabilitado={carrinhoOcupado} produto={nome} />
            <button type="button" className="remover-item" aria-label={`Remover ${nome} do carrinho`} disabled={carrinhoOcupado} onClick={async () => {
                setErro(null);
                try { await executarCarrinho(() => carrinhoApi.excluir(item.codigo, item.tamanho)); } catch (e) { setErro(e); }
            }}><i className="fa-solid fa-trash-can" aria-hidden="true" /><span>Remover</span></button>
        </div>
        {item.modificado && <p role="alert" className="mensagem">O preço mudou. Confira o valor atualizado antes de finalizar.</p>}
        <MensagemErro erro={erro} />
        </div>
    </article>;
}
