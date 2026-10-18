import { useState } from 'react';
import Formulario, { MensagemErro } from './Formulario';
import { dashboardApi } from '../services/dashboard';
import { useProcessamento } from '../context/ProcessamentoContext';
import UploadProdutoFotos from './UploadProdutoFotos';

const campos = [{ nome: 'path', label: 'URL da foto', tipo: 'url', maxLength: 2048, placeholder: 'https://exemplo.com/foto.jpg' }];

export default function ProdutoFotos({ produto, atualizar }) {
    const [previa, setPrevia] = useState('');
    const [revisao, setRevisao] = useState(0);
    const [erro, setErro] = useState(null);
    const { executar } = useProcessamento();
    return <section className="dashboard-secao"><h2>Fotos do produto</h2>
        <UploadProdutoFotos produtoId={produto.id} atualizar={atualizar} />
        <MensagemErro erro={erro} />
        <div className="dashboard-fotos">{produto.imagens?.map((foto) => <article className="dashboard-foto" key={`${foto.id}-${foto.path}-${foto.principal}`}>
            <img src={foto.path} alt={`Foto de ${produto.nome}`} width="240" height="180" loading="lazy" />
            {foto.principal && <p>Foto principal</p>}
            <Formulario bloquearTela campos={campos} valores={foto} botao="Salvar foto" enviar={async (dados) => {
                await dashboardApi.atualizarFoto(produto.id, foto.id, { path: dados.path, principal: dados.principal === 'on' });
                await atualizar();
            }}><label className="campo-checkbox"><input name="principal" type="checkbox" defaultChecked={foto.principal} />Foto principal</label></Formulario>
            <button onClick={async () => {
                if (!window.confirm('Remover esta foto do produto?')) return;
                setErro(null);
                try { await executar(async () => { await dashboardApi.excluirFoto(produto.id, foto.id); await atualizar(); }, 'Removendo foto'); }
                catch (e) { setErro(e); }
            }}>Remover foto</button>
        </article>)}</div>
        {!produto.imagens?.length && <p>Nenhuma foto cadastrada.</p>}
        <details><summary>Adicionar foto por URL</summary>
        <Formulario key={revisao} bloquearTela campos={campos} botao="Adicionar foto" sucesso="" aoAlterarCampo={(nome, valor) => { if (nome === 'path') setPrevia(valor); }} enviar={async (dados) => {
            await dashboardApi.adicionarFoto(produto.id, { path: dados.path, principal: dados.principal === 'on' });
            await atualizar(); setPrevia(''); setRevisao((valor) => valor + 1);
        }}><label className="campo-checkbox"><input name="principal" type="checkbox" />Usar como foto principal</label>
            {/^https?:\/\//i.test(previa) && <img className="dashboard-foto-previa" src={previa} alt="Prévia da nova foto" width="240" height="180" />}
        </Formulario></details>
    </section>;
}
