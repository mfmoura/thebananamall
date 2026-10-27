import { marca } from '../marca';
import { Link } from 'react-router-dom';

export default function Home() {
    return <section className="hello" aria-labelledby="hello-titulo">
        <span className="hello-detalhe" aria-hidden="true" />
        <p className="sobretitulo">{marca.nome}</p>
        <h1 id="hello-titulo">Bem-vindo<span>.</span></h1>
        <p className="hello-descricao">Nossa loja começa aqui.</p>
        <Link to="/produtos">Conheça nossos produtos <i className="fa-solid fa-arrow-right" aria-hidden="true" /></Link>
    </section>;
}
