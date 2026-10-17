import { useRef } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Formulario, { MensagemErro } from '../components/Formulario';
import { api } from '../services/api';
import useDados from '../hooks/useDados';
import { useLoja } from '../context/LojaContext';
import { dinheiro } from './Catalogo';
import ItemCarrinho from '../components/ItemCarrinho';

const campos = [
    { nome: 'nome', label: 'Nome completo / Razão social', maxLength: 60 },
    { nome: 'documento', label: 'CPF ou CNPJ (somente números)', pattern: '[0-9]{11}|[0-9]{14}', inputMode: 'numeric' },
    { nome: 'email', label: 'E-mail para a nota', tipo: 'email' },
    { nome: 'logradouro', label: 'Logradouro', maxLength: 60 },
    { nome: 'numero', label: 'Número', maxLength: 60 },
    { nome: 'bairro', label: 'Bairro', maxLength: 60 },
    { nome: 'cidade', label: 'Cidade', maxLength: 60 },
    { nome: 'codigo_municipio', label: 'Código IBGE do município', pattern: '[0-9]{7}', inputMode: 'numeric' },
    { nome: 'estado', label: 'UF', pattern: '[A-Z]{2}', maxLength: 2 },
    { nome: 'cep', label: 'CEP (somente números)', pattern: '[0-9]{8}', inputMode: 'numeric' },
];

export default function Checkout() {
    const { usuario, carrinho, executarCarrinho, carrinhoOcupado } = useLoja();
    const { dados: formas, erro, carregando } = useDados(api.formasPagamento, 'formas');
    const chave = useRef(sessionStorage.getItem('lcb.checkout-key') || crypto.randomUUID());
    const navigate = useNavigate();
    if (!usuario?.cliente) return <section className="tela"><h1>Checkout</h1><p>Somente uma conta Cliente pode finalizar a compra.</p></section>;
    if (!carrinho.itens?.length) return <section className="tela"><h1>Checkout</h1><p>Seu carrinho está vazio.</p><Link to="/produtos">Ver produtos</Link></section>;
    return <section className="tela tela-form"><h1>Finalizar compra</h1>
        <div aria-busy={carrinhoOcupado}>{carrinho.itens.map((item) => <ItemCarrinho key={item.codigo} item={item} />)}</div>
        <h2>Total dos produtos: {dinheiro(carrinho.itens.reduce((soma, item) => soma + Math.round(Number(item.preco_atual ?? item.preco ?? 0) * 100 * item.quantidade), 0) / 100)}</h2>
        {carrinho.itens.some((item) => item.modificado) && <p className="mensagem" role="alert">Há produtos cujo preço mudou. Confira os valores atualizados no <Link to="/carrinho">carrinho</Link> antes de confirmar.</p>}
        <p>Seus dados serão processados. A nota será emitida após a confirmação do pagamento.</p>
        <p className="mensagem">A cobrança pelo gateway ainda não está disponível. Esta etapa registra o pedido aguardando pagamento.</p>
        <MensagemErro erro={erro} />{carregando ? <p>Carregando opções…</p> : formas?.length > 0 && <Formulario desabilitado={carrinhoOcupado} campos={campos} valores={{ email: usuario.email, ...(usuario.enderecos?.[0] || {}) }} botao="Confirmar pedido" enviar={async (dados) => {
            sessionStorage.setItem('lcb.checkout-key', chave.current);
            const { nome, documento, email, forma_pagamento_id, ...endereco } = dados;
            let pedido;
            await executarCarrinho(async () => {
                pedido = await api.comprar({ forma_pagamento_id: Number(forma_pagamento_id), dados_fiscais: { nome, documento, email, indicador_ie: 9, endereco } }, chave.current);
                sessionStorage.removeItem('lcb.checkout-key');
            });
            navigate(`/pedidos/${pedido.id}`, { replace: true });
        }}>
            <label>Forma de pagamento<select name="forma_pagamento_id" required><option value="">Selecione</option>{formas.map((forma) => <option key={forma.id} value={forma.id}>{forma.nome}</option>)}</select></label>
            <p>Consumidor final não contribuinte de ICMS. Para outros enquadramentos, entre em contato.</p>
        </Formulario>}
        {!carregando && formas?.length === 0 && <p>Nenhuma forma de pagamento disponível.</p>}
    </section>;
}
