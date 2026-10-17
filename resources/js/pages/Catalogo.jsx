import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../services/api';
import { carrinhoApi } from '../services/carrinho';
import { useLoja } from '../context/LojaContext';
import useDados from '../hooks/useDados';
import { MensagemErro } from '../components/Formulario';
import usePaginacao from '../hooks/usePaginacao';
import GaleriaProduto from '../components/GaleriaProduto';
import ControleQuantidade from '../components/ControleQuantidade';
import ProdutoSkeleton from '../components/ProdutoSkeleton';

export const dinheiro = (valor) => Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const preco = (produto) => produto.preco;

function Produto({ produto, detalhe = false }) {
    const { executarCarrinho, carrinhoOcupado } = useLoja();
    const [quantidade, setQuantidade] = useState(1);
    const [quantidadeValida, setQuantidadeValida] = useState(true);
    const [erro, setErro] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const [mensagem, setMensagem] = useState('');
    const imagem = produto.imagens?.find((item) => item.principal) || produto.imagens?.[0];
    return <article className="produto-card">
        {detalhe ? <GaleriaProduto key={produto.id} produto={produto} /> : imagem && <Link to={`/produtos/${produto.id}`}><img src={imagem.path} alt={produto.nome} width="800" height="800" loading="lazy" decoding="async" /></Link>}
        <h2><Link to={`/produtos/${produto.id}`}>{produto.nome}</Link></h2>
        {detalhe && produto.descricao && <div className="produto-descricao"><h3>Descrição</h3><p>{produto.descricao}</p></div>}
        <p>{produto.categoria}</p><strong>{preco(produto) == null ? 'Preço indisponível' : dinheiro(preco(produto))}</strong>
        <p>Disponível: {Number(produto.estoque).toLocaleString('pt-BR')}</p>
        <MensagemErro erro={erro} />{mensagem && <p role="status">{mensagem}</p>}
        <ControleQuantidade valor={quantidade} alterar={setQuantidade} validadeAlterada={setQuantidadeValida} produto={produto.nome} desabilitado={ocupado || carrinhoOcupado || !(produto.estoque > 0)} />
        <button className="botao-principal" disabled={!quantidadeValida || ocupado || carrinhoOcupado || !(produto.estoque > 0) || preco(produto) == null} onClick={async () => {
            setOcupado(true); setErro(null); setMensagem('');
            try { await executarCarrinho(() => carrinhoApi.adicionar(produto.id, quantidade)); setMensagem('Quantidade adicionada ao carrinho.'); }
            catch (e) { setErro(e); } finally { setOcupado(false); }
        }}>{ocupado ? 'Adicionando…' : 'Adicionar ao carrinho'}</button>
    </article>;
}

function ListaProdutos() {
    const { itens, erro, carregando, temMais, carregarMais } = usePaginacao(api.produtos);
    const sentinela = useRef(null);
    useEffect(() => {
        if (!temMais || carregando || erro || !window.IntersectionObserver) return;
        const observer = new IntersectionObserver(([entrada]) => {
            if (entrada.isIntersecting) carregarMais();
        }, { rootMargin: '200px' });
        if (sentinela.current) observer.observe(sentinela.current);
        return () => observer.disconnect();
    }, [temMais, carregando, erro, carregarMais]);
    return <section className="tela"><h1>Nossa loja</h1><MensagemErro erro={erro} />
        <div className="produtos-grid" aria-busy={carregando}>{itens.map((produto) => <Produto key={produto.id} produto={produto} />)}{carregando && temMais && <ProdutoSkeleton />}</div>
        {temMais && <div className="paginacao-catalogo" ref={sentinela}>
            {temMais && !carregando && <button onClick={carregarMais}>{erro ? 'Tentar novamente' : 'Carregar mais produtos'}</button>}
        </div>}
        {!carregando && !erro && !itens.length && <p>Nenhum produto disponível no momento.</p>}
    </section>;
}

function DetalheProduto({ id }) {
    const { dados, erro, carregando } = useDados(() => api.produto(id), id);
    return <section className="tela detalhe-produto"><Link to="/">Voltar para a loja</Link><h1>{dados?.nome || 'Produto'}</h1>
        <MensagemErro erro={erro} />{carregando ? <ProdutoSkeleton quantidade={1} /> : dados && <Produto produto={dados} detalhe />}
    </section>;
}

export default function Catalogo() {
    const { id } = useParams();
    return id ? <DetalheProduto key={id} id={id} /> : <ListaProdutos />;
}
