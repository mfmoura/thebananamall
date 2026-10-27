import { Link } from 'react-router-dom';
import { marca } from '../marca';

export default function Rodape() {
    return <footer className="rodape-loja">
        <div className="rodape-conteudo">
            <Link className="rodape-marca" to="/"><img src="/images/the-banana-mall-logo.png" alt={marca.nome} width="2172" height="724" loading="lazy" /></Link>
            <nav className="rodape-links" aria-label="Links da loja">
                <Link to="/produtos">Produtos</Link>
                <Link to="/conta">Minha conta</Link>
            </nav>
        </div>
    </footer>;
}