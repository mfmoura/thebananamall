import { useState } from 'react';

export default function GaleriaProduto({ produto }) {
    const principal = produto.imagens?.find((imagem) => imagem.principal) || produto.imagens?.[0];
    const imagens = principal ? [principal, ...(produto.imagens || []).filter((imagem) => imagem.id !== principal.id)] : [];
    const [indice, setIndice] = useState(0);
    if (!principal) return <p className="imagem-indisponivel">Imagem indisponível</p>;
    const posicao = indice % imagens.length;
    const atual = imagens[posicao];
    return <div className="galeria-produto">
        <section className="carrossel-produto" aria-label="Imagens do produto" aria-roledescription="carrossel">
            <img src={atual.path} alt={`${produto.nome} — imagem ${posicao + 1}`} width="800" height="800" loading={posicao === 0 ? 'eager' : 'lazy'} decoding="async" />
            <div className="carrossel-controles">
                <button type="button" aria-label="Imagem anterior" disabled={imagens.length < 2} onClick={() => setIndice((valor) => (valor - 1 + imagens.length) % imagens.length)}><i className="fa-solid fa-chevron-left" aria-hidden="true" /></button>
                <span aria-live="polite">{posicao + 1} / {imagens.length}</span>
                <button type="button" aria-label="Próxima imagem" disabled={imagens.length < 2} onClick={() => setIndice((valor) => (valor + 1) % imagens.length)}><i className="fa-solid fa-chevron-right" aria-hidden="true" /></button>
            </div>
        </section>
    </div>;
}
