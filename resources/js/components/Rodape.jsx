const site = 'https://ligacomunistabrasileira.org';
const paginas = [
    ['Publicações.', '/notas-politicas/'],
    ['Resoluções.', '/resolucoes/'],
    ['Formação.', '/formacao/'],
    ['Sobre.', '/sobre/'],
];
const redes = [
    ['Instagram', 'https://www.instagram.com/ligacomunistabrasileira/', 'fa-instagram'],
    ['Facebook', 'https://www.facebook.com/LigaComunistaBrasileira', 'fa-facebook'],
    ['YouTube', 'https://www.youtube.com/@LigaComunistaBrasileira', 'fa-youtube'],
];

export default function Rodape() {
    return <footer className="rodape-loja">
        <div className="rodape-conteudo">
            <a className="rodape-marca" href={`${site}/`} aria-label="Site da Liga Comunista Brasileira">
                <img src="/images/lcb-logo-rodape.png" alt="Liga Comunista Brasileira" width="932" height="370" loading="lazy" decoding="async" />
            </a>
            <nav className="rodape-links" aria-label="Links institucionais da Liga">
                {paginas.map(([nome, path]) => <a key={path} href={`${site}${path}`}>{nome}</a>)}
            </nav>
            <nav className="rodape-redes" aria-label="Redes sociais da Liga">
                {redes.map(([nome, href, icone]) => <a key={nome} href={href} target="_blank" rel="noopener noreferrer" aria-label={`${nome} da Liga (abre em nova aba)`}><i className={`fa-brands ${icone}`} aria-hidden="true" /></a>)}
            </nav>
        </div>
    </footer>;
}
