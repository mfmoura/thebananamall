import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import Formulario, { MensagemErro } from '../../components/Formulario';
import ProdutoFotos from '../../components/ProdutoFotos';
import PaginaSkeleton from '../../components/PaginaSkeleton';
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
    if (carregando) return <PaginaSkeleton rotulo="Carregando formulário" />;
    if (erro) return <><MensagemErro erro={erro} /><Link to="/dashboard/produtos">Voltar para produtos</Link></>;
    return <div className="dashboard-editor"><Link to="/dashboard/produtos">← Voltar para produtos</Link><h1>{id ? 'Editar produto' : 'Novo produto'}</h1>
        <section className="dashboard-secao"><h2>Dados do produto</h2>
            <Formulario key={id || 'novo'} bloquearTela campos={[
                { nome: 'codigo', label: 'Código', maxLength: 80 },
                { nome: 'nome', label: 'Nome', maxLength: 255 },
                { nome: 'grade_texto', label: 'Grade de tamanhos (separados por vírgula)', obrigatorio: false, maxLength: 1500 },
                { nome: 'peso_gramas', label: 'Peso para envio, com embalagem (g)', tipo: 'number', min: 1, max: 30000, step: 1, obrigatorio: false },
                ...['altura', 'largura', 'comprimento'].map((medida) => ({ nome: `${medida}_cm`, label: `${medida[0].toUpperCase() + medida.slice(1)} da embalagem unitária (cm)`, tipo: 'number', min: 0.01, max: 100, step: 0.01, obrigatorio: false })),
                ...(!id ? [precoCampo] : []),
            ]} valores={{ ...produto, grade_texto: produto?.grade?.join(', ') || '' }} botao={id ? 'Salvar alterações' : 'Cadastrar e adicionar fotos'} sucesso={id ? 'Produto atualizado.' : ''} enviar={async (dadosForm) => {
                const payload = { codigo: dadosForm.codigo, nome: dadosForm.nome, descricao: dadosForm.descricao || null, categoria: dadosForm.categoria, ativo: dadosForm.ativo === 'on' };
                payload.grade = dadosForm.grade_texto.trim() ? dadosForm.grade_texto.split(',').map((valor) => valor.trim()) : [];
                for (const campo of ['peso_gramas', 'altura_cm', 'largura_cm', 'comprimento_cm']) payload[campo] = dadosForm[campo] || null;
                payload.embalagem_estimada = dadosForm.embalagem_estimada === 'on';
                if (id) { await dashboardApi.atualizar(id, payload); await atualizar(); }
                else { const criado = await dashboardApi.criar({ ...payload, preco: dadosForm.preco }); navigate(`/dashboard/produtos/${criado.id}/editar`, { replace: true }); }
            }}>
                <label>Categoria<select name="categoria" defaultValue={produto?.categoria || ''} required><option value="">Selecione</option>{dados.categorias.map((item) => <option key={item.id} value={item.nome_codigo}>{item.nome}</option>)}</select></label>
                <label>Descrição<textarea name="descricao" rows={5} maxLength={10000} defaultValue={produto?.descricao || ''} /></label>
                <label className="campo-checkbox"><input name="ativo" type="checkbox" defaultChecked={produto?.ativo ?? true} />Produto ativo</label>
                <label className="campo-checkbox"><input name="embalagem_estimada" type="checkbox" defaultChecked={produto?.embalagem_estimada ?? false} />Peso e medidas estimados (pendentes de conferência)</label>
                <p>Preencha os quatro campos de envio para disponibilizar cotação. Use o peso com embalagem e as medidas do produto preparado para envio.</p>
                <p>Exemplos de grade: P, M, G, GG ou 38, 40, 42. Deixe vazio para produtos sem tamanho. O preço é comum à grade; o estoque é separado por tamanho. Tamanhos cadastrados devem ser preservados. Produtos com movimentações sem tamanho exigem um novo cadastro para usar grade.</p>
            </Formulario>
        </section>
        {id && <><section className="dashboard-secao"><h2>Preço</h2><p>Preço atual: {produto.preco == null ? 'não cadastrado' : Number(produto.preco).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</p>
            <Formulario key={produto.preco} bloquearTela campos={[precoCampo]} valores={{ preco: produto.preco || '' }} botao="Atualizar preço" enviar={async (valor) => { await dashboardApi.preco(id, valor); await atualizar(); }} />
        </section><ProdutoFotos produto={produto} atualizar={atualizar} /></>}
        {!id && <section className="dashboard-secao"><h2>Fotos</h2><p>Após cadastrar o produto, adicione as fotos na próxima etapa.</p></section>}
    </div>;
}
