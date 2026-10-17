export default function ProdutoSkeleton({ quantidade = 5 }) {
    return <>
        <span className="sr-only" role="status">Carregando produtos…</span>
        {Array.from({ length: quantidade }, (_, indice) => <div className="produto-card produto-skeleton" key={indice} aria-hidden="true">
            <div className="skeleton-bloco skeleton-imagem" />
            <div className="skeleton-bloco skeleton-titulo" />
            <div className="skeleton-bloco skeleton-linha" />
            <div className="skeleton-bloco skeleton-preco" />
            <div className="skeleton-bloco skeleton-linha" />
            <div className="skeleton-bloco skeleton-botao" />
        </div>)}
    </>;
}
