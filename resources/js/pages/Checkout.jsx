import { useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Formulario, { MensagemErro } from '../components/Formulario';
import { api } from '../services/api';
import useDados from '../hooks/useDados';
import { useLoja } from '../context/LojaContext';
import { dinheiro } from './Catalogo';
import ItemCarrinho from '../components/ItemCarrinho';
import BloqueioAutenticacao from '../components/BloqueioAutenticacao';

const identificacao = [
    { nome: 'nome', label: 'Nome completo / Razão social', maxLength: 60 },
    { nome: 'documento', label: 'CPF ou CNPJ', inputMode: 'numeric', maxLength: 18, mascara: 'cpf-cnpj', pattern: '[0-9]{11}|[0-9]{14}|[0-9]{3}[.][0-9]{3}[.][0-9]{3}-[0-9]{2}|[0-9]{2}[.][0-9]{3}[.][0-9]{3}/[0-9]{4}-[0-9]{2}' },
    { nome: 'email', label: 'E-mail para a nota', tipo: 'email' },
];
const enderecoCompleto = [
    { nome: 'logradouro', label: 'Logradouro', maxLength: 60 },
    { nome: 'numero', label: 'Número', maxLength: 60 },
    { nome: 'complemento', label: 'Complemento', maxLength: 60, obrigatorio: false },
    { nome: 'bairro', label: 'Bairro', maxLength: 60 },
    { nome: 'cidade', label: 'Cidade', maxLength: 60 },
    { nome: 'estado', label: 'UF', pattern: '[A-Z]{2}', maxLength: 2 },
    { nome: 'cep', label: 'CEP', inputMode: 'numeric', maxLength: 9, pattern: '[0-9]{5}-?[0-9]{3}', mascara: 'cep' },
];

function mascararDocumento(valor = '') {
    const numero = String(valor).replace(/\D/g, '');
    return numero.length === 11
        ? numero.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4')
        : numero.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/, '$1.$2.$3/$4-$5');
}

export default function Checkout() {
    const { usuario, carrinho, executarCarrinho, carrinhoOcupado } = useLoja();
    const { dados: formas, erro, carregando } = useDados(api.formasPagamento, 'formas');
    const chave = useRef(sessionStorage.getItem('lcb.checkout-key') || crypto.randomUUID());
    const [processandoPedido, setProcessandoPedido] = useState(false);
    const navigate = useNavigate();
    const dadosSalvos = usuario?.cliente?.dados_fiscais || null;
    const enderecoSalvo = usuario?.enderecos?.[0]
        ? { ...(dadosSalvos?.endereco || {}), ...usuario.enderecos[0] }
        : dadosSalvos?.endereco || null;
    if (!usuario?.cliente) return <section className="tela"><h1>Checkout</h1><p>Somente uma conta Cliente pode finalizar a compra.</p></section>;
    if (!carrinho.itens?.length) return <section className="tela"><h1>Checkout</h1><p>Seu carrinho está vazio.</p><Link to="/produtos">Ver produtos</Link></section>;
    return <section className="tela tela-form checkout"><h1>Finalizar compra</h1>
        <div aria-busy={carrinhoOcupado}>{carrinho.itens.map((item) => <ItemCarrinho key={item.codigo} item={item} />)}</div>
        <h2>Total dos produtos: {dinheiro(carrinho.itens.reduce((soma, item) => soma + Math.round(Number(item.preco_atual ?? item.preco ?? 0) * 100 * item.quantidade), 0) / 100)}</h2>
        {carrinho.itens.some((item) => item.modificado) && <p className="mensagem" role="alert">Há produtos cujo preço mudou. Confira os valores atualizados no <Link to="/carrinho">carrinho</Link> antes de confirmar.</p>}
        <p>Seus dados serão processados. A nota será emitida após a confirmação do pagamento.</p>
        <p>Depois de confirmar os dados, você seguirá para o pagamento por Pix, boleto ou cartão com Mercado Pago.</p>
        <MensagemErro erro={erro} />{carregando ? <p>Carregando opções…</p> : formas?.length > 0 && <Formulario desabilitado={carrinhoOcupado} campos={[
            ...(dadosSalvos ? [] : identificacao),
            ...(enderecoSalvo ? [{ nome: 'endereco_confirmado', label: dadosSalvos ? 'Confirmo os dados de identificação e o endereço salvos abaixo.' : 'Confirmo o endereço salvo abaixo.', tipo: 'checkbox' }] : enderecoCompleto),
        ]} valores={{ email: dadosSalvos?.email || usuario.email, ...(enderecoSalvo || {}) }} botao="Confirmar pedido" textoOcupado="Confirmar pedido" enviar={async (dados) => {
            setProcessandoPedido(true);
            try {
                sessionStorage.setItem('lcb.checkout-key', chave.current);
                const endereco = enderecoSalvo
                    ? { logradouro: enderecoSalvo.logradouro, numero: enderecoSalvo.numero, complemento: enderecoSalvo.complemento || '', cidade: enderecoSalvo.cidade, estado: enderecoSalvo.estado, cep: String(enderecoSalvo.cep).replace(/\D/g, '') }
                    : { logradouro: dados.logradouro, numero: dados.numero, complemento: dados.complemento || '', bairro: dados.bairro, cidade: dados.cidade, estado: dados.estado.toUpperCase(), cep: dados.cep };
                if (enderecoSalvo && !dados.endereco_confirmado) throw new Error('Confirme o endereço de entrega para continuar.');
                const cep = await api.consultarCep(endereco.cep);
                const bairro = endereco.bairro || cep.bairro;
                if (!bairro) throw new Error('O ViaCEP não retornou o bairro. Atualize o endereço da sua conta antes de continuar.');
                endereco.bairro = bairro;
                endereco.codigo_municipio = cep.codigo_municipio;
                let pedido;
                await executarCarrinho(async () => {
                    pedido = await api.comprar({ forma_pagamento_id: Number(dados.forma_pagamento_id), dados_fiscais: { nome: dados.nome || dadosSalvos?.nome, documento: dados.documento || dadosSalvos?.documento, email: dados.email || dadosSalvos?.email || usuario.email, indicador_ie: 9, endereco } }, chave.current);
                    sessionStorage.removeItem('lcb.checkout-key');
                });
                navigate(`/checkout/pagamento/${pedido.id}`, { replace: true });
            } finally { setProcessandoPedido(false); }
        }}>
            {enderecoSalvo && <article className="produto-card"><h2>Dados salvos</h2>{dadosSalvos && <p>{dadosSalvos.nome} · {mascararDocumento(dadosSalvos.documento)} · {dadosSalvos.email}</p>}<p>{enderecoSalvo.logradouro}, {enderecoSalvo.numero}{enderecoSalvo.complemento ? `, ${enderecoSalvo.complemento}` : ''}</p><p>{enderecoSalvo.cidade}/{enderecoSalvo.estado} · CEP {String(enderecoSalvo.cep).replace(/^(\d{5})(\d{3})$/, '$1-$2')}</p><Link to="/conta">Alterar endereço</Link></article>}
            <label>Forma de pagamento<select name="forma_pagamento_id" required><option value="">Selecione</option>{formas.filter((forma) => ['pix', 'cartao_credito', 'cartao_debito'].includes(forma.codigo)).map((forma) => <option key={forma.id} value={forma.id}>{forma.nome}</option>)}</select></label>
            <p>Consumidor final não contribuinte de ICMS. Para outros enquadramentos, entre em contato.</p>
        </Formulario>}
        {!carregando && formas?.length === 0 && <p>Nenhuma forma de pagamento disponível.</p>}
        {processandoPedido && <BloqueioAutenticacao rotulo="Processando pedido" />}
    </section>;
}
