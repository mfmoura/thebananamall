import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Formulario, { MensagemErro } from '../../components/Formulario';
import ProdutoFotos from '../../components/ProdutoFotos';
import BloqueioAutenticacao from '../../components/BloqueioAutenticacao';
import useDados from '../../hooks/useDados';
import { dashboardApi } from '../../services/dashboard';

const precoCampo = { nome: 'preco', label: 'Preço (R$)', tipo: 'number', min: '0.01', step: '0.01' };

export default function ProdutoFormulario() {
    const { id } = useParams();
    const navigate = useNavigate();
    const [produtoAtual, setProdutoAtual] = useState(null);
    const { dados, erro, carregando } = useDados(async () => {
        const [categorias, produto] = await Promise.all([dashboardApi.categorias(), id ? dashboardApi.produto(id) : Promise.resolve(null)]);
        return { categorias, produto };
    }, id);
    const produto = produtoAtual || dados?.produto;
    const atualizar = async () => setProdutoAtual(await dashboardApi.produto(id));
    if (carregando) return <BloqueioAutenticacao rotulo="Carregando formulário" />;
    if (erro) return <><MensagemErro erro={erro} /><Link to="/dashboard/produtos">Voltar para produtos</Link></>;
    return <div className="dashboard-editor"><Link to="/dashboard/produtos">← Voltar para produtos</Link><h1>{id ? 'Editar produto' : 'Novo produto'}</h1>
        <section className="dashboard-secao"><h2>Dados do produto</h2>
            <Formulario key={id || 'novo'} bloquearTela campos={[
                { nome: 'codigo', label: 'Código', maxLength: 80 },
                { nome: 'nome', label: 'Nome', maxLength: 255 },
                ...(!id ? [precoCampo] : []),
            ]} valores={produto || {}} botao={id ? 'Salvar alterações' : 'Cadastrar e adicionar fotos'} sucesso={id ? 'Produto atualizado.' : ''} enviar={async (dadosForm) => {
                const payload = { codigo: dadosForm.codigo, nome: dadosForm.nome, descricao: dadosForm.descricao || null, categoria: dadosForm.categoria, ativo: dadosForm.ativo === 'on' };
                if (id) { await dashboardApi.atualizar(id, payload); await atualizar(); }
                else { const criado = await dashboardApi.criar({ ...payload, preco: dadosForm.preco }); navigate(`/dashboard/produtos/${criado.id}/editar`, { replace: true }); }
            }}>
                <label>Categoria<select name="categoria" defaultValue={produto?.categoria || ''} required><option value="">Selecione</option>{dados.categorias.map((item) => <option key={item.id} value={item.nome_codigo}>{item.nome}</option>)}</select></label>
                <label>Descrição<textarea name="descricao" rows={5} maxLength={10000} defaultValue={produto?.descricao || ''} /></label>
                <label className="campo-checkbox"><input name="ativo" type="checkbox" defaultChecked={produto?.ativo ?? true} />Produto ativo</label>
            </Formulario>
        </section>
        {id && <><section className="dashboard-secao"><h2>Preço</h2><p>Preço atual: {produto.preco == null ? 'não cadastrado' : Number(produto.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</p>
            <Formulario key={produto.preco} bloquearTela campos={[precoCampo]} valores={{ preco: produto.preco || '' }} botao="Atualizar preço" enviar={async (valor) => { await dashboardApi.preco(id, valor); await atualizar(); }} />
        </section><ProdutoFotos produto={produto} atualizar={atualizar} /></>}
        {!id && <section className="dashboard-secao"><h2>Fotos</h2><p>Após cadastrar o produto, adicione as fotos na próxima etapa.</p></section>}
    </div>;
}
