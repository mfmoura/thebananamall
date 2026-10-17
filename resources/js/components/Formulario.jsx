import { useState } from 'react';
import { mensagemValidacao } from '../services/validacaoFormulario';
import CampoSenha from './CampoSenha';

export function MensagemErro({ erro }) {
    if (!erro) return null;
    return <div className="mensagem erro" role="alert"><p>{erro.message || erro}</p>{erro.data?.errors && <ul>{Object.entries(erro.data.errors).flatMap(([campo, mensagens]) => mensagens.map((mensagem, i) => <li key={`${campo}-${i}`}>{mensagem}</li>))}</ul>}</div>;
}

export default function Formulario({ campos, enviar, botao = 'Salvar', valores = {}, sucesso = 'Dados salvos.', children, desabilitado = false, aoAlterarCampo, textoOcupado = 'Processando…' }) {
    const [erro, setErro] = useState(null);
    const [mensagem, setMensagem] = useState('');
    const [ocupado, setOcupado] = useState(false);
    return <form className="formulario" onInvalid={(event) => event.target.setCustomValidity(mensagemValidacao(event.target))} onInput={(event) => event.target.setCustomValidity?.('')} onChange={(event) => {
        event.target.setCustomValidity?.('');
        aoAlterarCampo?.(event.target.name, event.target.value, event.currentTarget);
    }} onSubmit={async (event) => {
        event.preventDefault();
        if (ocupado || desabilitado) return;
        const form = event.currentTarget;
        setOcupado(true); setErro(null); setMensagem('');
        try {
            await enviar(Object.fromEntries(new FormData(form)));
            form.querySelectorAll('input[data-campo-senha]').forEach((input) => { input.value = ''; });
            setMensagem(sucesso);
        } catch (e) { setErro(e); } finally { setOcupado(false); }
    }}>
        <MensagemErro erro={erro} />
        {mensagem && <p className="mensagem" role="status">{mensagem}</p>}
        {campos.map(({ nome, label, tipo = 'text', obrigatorio = true, ...props }) => tipo === 'password'
            ? <CampoSenha key={nome} label={label} name={nome} defaultValue={valores[nome] ?? ''} required={obrigatorio} {...props} />
            : <label key={nome}>{label}<input name={nome} type={tipo} defaultValue={valores[nome] ?? ''} required={obrigatorio} {...props} /></label>)}
        {children}
        <button className="botao-principal" disabled={ocupado || desabilitado}>{ocupado ? textoOcupado : botao}</button>
    </form>;
}

export const senhaCampos = [
    { nome: 'password', label: 'Nova senha (8 a 15 caracteres)', tipo: 'password', minLength: 8, maxLength: 15, autoComplete: 'new-password' },
    { nome: 'password_confirmation', label: 'Confirme a senha', tipo: 'password', minLength: 8, maxLength: 15, autoComplete: 'new-password' },
];
