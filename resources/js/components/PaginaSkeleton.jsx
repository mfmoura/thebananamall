export default function PaginaSkeleton({ rotulo = 'Carregando página' }) {
    return <section className="tela" role="status" aria-label={rotulo} aria-busy="true">
        <div className="skeleton-bloco skeleton-titulo" aria-hidden="true" />
        <div className="skeleton-bloco skeleton-linha" aria-hidden="true" />
        <div className="skeleton-bloco skeleton-linha" aria-hidden="true" />
    </section>;
}
