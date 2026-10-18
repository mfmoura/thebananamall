import { useRef, useState } from 'react';
import useUploadImagens from '../hooks/useUploadImagens';
import { dashboardApi } from '../services/dashboard';
import { useProcessamento } from '../context/ProcessamentoContext';
import { MensagemErro } from './Formulario';

export default function UploadProdutoFotos({ produtoId, atualizar }) {
    const [ocupado, setOcupado] = useState(false);
    const [principal, setPrincipal] = useState(false);
    const [erro, setErro] = useState(null);
    const [mensagem, setMensagem] = useState('');
    const enviando = useRef(false);
    const upload = useUploadImagens({ desabilitado: ocupado });
    const { executar } = useProcessamento();

    const enviar = async () => {
        if (enviando.current || !upload.arquivos.length) return;
        enviando.current = true; setOcupado(true); setErro(null); setMensagem('');
        let enviados = 0;
        try {
            await executar(async () => {
                try {
                    for (const item of upload.arquivos) {
                        try { await dashboardApi.enviarFoto(produtoId, item.arquivo, principal && enviados === 0); }
                        catch (falha) { throw Object.assign(new Error(`${item.arquivo.name}: ${falha.message}`), { data: falha.data }); }
                        enviados++;
                        upload.remover(item.id);
                    }
                } finally {
                    if (enviados) { setPrincipal(false); await atualizar(); }
                }
            }, 'Enviando fotos');
        } catch (falha) { setErro(falha); }
        finally {
            if (enviados) setMensagem(`${enviados} foto(s) salva(s).`);
            enviando.current = false; setOcupado(false);
        }
    };

    return <div className="upload-produto" aria-busy={ocupado}>
        <h3>Enviar arquivos</h3>
        <p id="upload-instrucoes">JPEG, PNG ou WebP, até 5 MB e 6000 × 6000 pixels por imagem. Selecione até 10 arquivos por vez.</p>
        <div {...upload.zonaProps} className={`upload-zona${upload.arrastando ? ' arrastando' : ''}`} role="group" aria-label="Adicionar fotos do produto" aria-describedby="upload-instrucoes upload-colar">
            <i className="fa-solid fa-cloud-arrow-up" aria-hidden="true" />
            <p>Arraste as imagens para esta área</p>
            <button type="button" disabled={ocupado} onClick={() => upload.inputRef.current?.click()}>Selecionar arquivos</button>
            <input ref={upload.inputRef} {...upload.inputProps} className="sr-only" tabIndex={-1} aria-label="Selecionar imagens para upload" />
            <p id="upload-colar">Ou foque esta área e use Ctrl+V (⌘V no Mac) para colar uma imagem.</p>
        </div>
        <MensagemErro erro={upload.erro || erro} />
        {mensagem && <p role="status">{mensagem}</p>}
        {upload.arquivos.length > 0 && <>
            <ul className="upload-previas">{upload.arquivos.map((item) => <li key={item.id}>
                <img src={item.previa} alt={`Prévia de ${item.arquivo.name}`} width="160" height="120" />
                <span>{item.arquivo.name}</span>
                <button type="button" disabled={ocupado} onClick={() => upload.remover(item.id)} aria-label={`Remover ${item.arquivo.name} da seleção`}>Remover</button>
            </li>)}</ul>
            <label className="campo-checkbox"><input type="checkbox" checked={principal} disabled={ocupado} onChange={(event) => setPrincipal(event.target.checked)} />Usar a primeira imagem enviada como principal</label>
            <button type="button" className="botao-principal" disabled={ocupado} onClick={enviar}>{ocupado ? 'Enviando…' : `Enviar ${upload.arquivos.length} foto(s)`}</button>
        </>}
    </div>;
}
