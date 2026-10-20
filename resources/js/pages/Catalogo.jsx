import { useEffect, useRef, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api } from '../services/api';
import { carrinhoApi } from '../services/carrinho';
import { useLoja } from '../context/LojaContext';
import useDados from '../hooks/useDados';
import { MensagemErro } from '../components/Formulario';
import useFiltroProdutos from '../hooks/useFiltroProdutos';
import GaleriaProduto from '../components/GaleriaProduto';
import ControleQuantidade from '../components/ControleQuantidade';
import ProdutoSkeleton from '../components/ProdutoSkeleton';

export const dinheiro = (valor) => Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const preco = (produto) => produto.preco;

function Produto({ produto, detalhe = false }) {
    const { executarCarrinho, carrinhoOcupado } = useLoja();
    const [quantidade, setQuantidade] = useState(1);
    const [tamanho, setTamanho] = useState('');
    const temGrade = Boolean(produto.grade?.length);
    const saldo = temGrade ? produto.estoque_por_tamanho?.find((item) => item.tamanho === tamanho)?.estoque ?? 0 : produto.estoque;
    const [quantidadeValida, setQuantidadeValida] = useState(true);
    const [erro, setErro] = useState(null);
    const [ocupado, setOcupado] = useState(false);
    const [mensagem, setMensagem] = useState('');
    const imagem = produto.imagens?.find((item) => item.principal) || produto.imagens?.[0];
    return <article className="produto-card">
        {detalhe ? <GaleriaProduto key={produto.id} produto={produto} /> : <Link className="produto-imagem" to={`/produtos/${produto.id}`}>{imagem ? <img src={imagem.path} alt={produto.nome} width="800" height="800" loading="lazy" decoding="async" /> : <span className="imagem-indisponivel">Imagem indisponível</span>}</Link>}
        <p className="produto-categoria">{produto.categoria_cadastro?.nome}</p>
        <h2><Link to={`/produtos/${produto.id}`}>{produto.nome}</Link></h2>
        {detalhe && produto.descricao && <div className="produto-descricao"><h3>Descrição</h3><p>{produto.descricao}</p></div>}
        <strong>{preco(produto) == null ? 'Preço indisponível' : dinheiro(preco(produto))}</strong>
        {detalhe && <div className="produto-compra">
        <div className="produto-opcoes">
        <label className={!temGrade ? 'campo-reservado' : undefined} inert={!temGrade || undefined} aria-hidden={!temGrade || undefined}>Tamanho<select value={tamanho} onChange={(evento) => { setTamanho(evento.target.value); setQuantidade(1); }} disabled={!temGrade || ocupado || carrinhoOcupado} required={temGrade}><option value="">Selecione um tamanho</option>{produto.estoque_por_tamanho?.map((item) => <option key={item.tamanho} value={item.tamanho} disabled={item.estoque <= 0}>{item.tamanho}{item.estoque <= 0 ? ' — Esgotado' : ''}</option>)}</select></label>
        <ControleQuantidade valor={quantidade} alterar={setQuantidade} validadeAlterada={setQuantidadeValida} inteiro={temGrade} produto={produto.nome} desabilitado={ocupado || carrinhoOcupado || !(saldo > 0)} />
        </div>
        <p className="produto-disponibilidade" aria-live="polite">{!temGrade || tamanho ? `Disponível: ${Number(saldo).toLocaleString('pt-BR')}` : 'Selecione o tamanho para consultar o estoque.'}</p>
        <MensagemErro erro={erro} />{mensagem && <p role="status">{mensagem}</p>}
        <button className="botao-principal" disabled={!quantidadeValida || (temGrade && !Number.isInteger(Number(quantidade))) || ocupado || carrinhoOcupado || !(saldo > 0) || preco(produto) == null} onClick={async () => {
            setOcupado(true); setErro(null); setMensagem('');
            try { await executarCarrinho(() => carrinhoApi.adicionar(produto.id, quantidade, temGrade ? tamanho : null)); setMensagem('Quantidade adicionada ao carrinho.'); }
            catch (e) { setErro(e); } finally { setOcupado(false); }
        }}>{ocupado ? 'Adicionando…' : 'Adicionar ao carrinho'}</button>
        </div>}
    </article>;
}

function ListaProdutos() {
    const { dados: categorias, erro: erroCategorias } = useDados(() => api.categorias());
    const { itens, erro, carregando, temMais, carregarMais, categoria, setCategoria, busca, setBusca, porPagina } = useFiltroProdutos();
    const sentinela = useRef(null);
    useEffect(() => {
        if (!temMais || carregando || erro || !window.IntersectionObserver) return;
        const observer = new IntersectionObserver(([entrada]) => {
            if (entrada.isIntersecting) carregarMais();
        }, { rootMargin: '200px' });
        if (sentinela.current) observer.observe(sentinela.current);
        return () => observer.disconnect();
    }, [temMais, carregando, erro, carregarMais]);
    return <section className="tela"><h1>Nossa loja</h1><MensagemErro erro={erro} /><MensagemErro erro={erroCategorias} />
        <details className="painel-filtros-produtos">
        <summary><i className="fa-solid fa-filter" aria-hidden="true" /> Filtrar produtos</summary>
        <form className="formulario filtros-produtos" role="search" onSubmit={(evento) => evento.preventDefault()}>
            <label>Categoria<select value={categoria} onChange={(evento) => setCategoria(evento.target.value)}>
                <option value="">Todas as categorias</option>
                {(categorias || []).map((item) => <option key={item.id} value={item.nome_codigo}>{item.nome}</option>)}
            </select></label>
            <label>Buscar produtos<input type="search" value={busca} maxLength={255} placeholder="Nome, código ou descrição" onChange={(evento) => setBusca(evento.target.value)} /></label>
        </form>
        </details>
        <div className="produtos-grid" aria-busy={carregando}>{itens.map((produto) => <Produto key={produto.id} produto={produto} />)}{carregando && temMais && <ProdutoSkeleton quantidade={porPagina} />}</div>
        {temMais && <div className="paginacao-catalogo" ref={sentinela}>
            {temMais && !carregando && <button onClick={carregarMais}>{erro ? 'Tentar novamente' : 'Carregar mais produtos'}</button>}
        </div>}
        {!carregando && !erro && !itens.length && <p>Nenhum produto encontrado.</p>}
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
