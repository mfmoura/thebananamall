import { Link } from 'react-router-dom';

export default function Home() {
    return <section className="hello" aria-labelledby="hello-titulo">
        <span className="hello-detalhe" aria-hidden="true" />
        <p className="sobretitulo">Liga Comunista Brasileira</p>
        <h1 id="hello-titulo">Hello World<span>.</span></h1>
        <p className="hello-descricao">Nossa loja começa aqui.</p>
        <Link to="/produtos">Conheça nossos produtos <i className="fa-solid fa-arrow-right" aria-hidden="true" /></Link>
        <div className="hello-status"><i className="fa-solid fa-circle-check" aria-hidden="true" /><p role="status">React carregado com sucesso pelo NPM/Vite.</p></div>
    </section>;
}
