import { useState } from 'react';

export function MensagemErro({ erro }) {
    if (!erro) return null;
    return <div className="mensagem erro" role="alert"><p>{erro.message || erro}</p>{erro.data?.errors && <ul>{Object.entries(erro.data.errors).flatMap(([campo, mensagens]) => mensagens.map((mensagem, i) => <li key={`${campo}-${i}`}>{mensagem}</li>))}</ul>}</div>;
}

export default function Formulario({ campos, enviar, botao = 'Salvar', valores = {}, sucesso = 'Dados salvos.', children, desabilitado = false }) {
    const [erro, setErro] = useState(null);
    const [mensagem, setMensagem] = useState('');
    const [ocupado, setOcupado] = useState(false);
    return <form className="formulario" onSubmit={async (event) => {
        event.preventDefault();
        if (ocupado || desabilitado) return;
        const form = event.currentTarget;
        setOcupado(true); setErro(null); setMensagem('');
        try {
            await enviar(Object.fromEntries(new FormData(form)));
            form.querySelectorAll('input[type="password"]').forEach((input) => { input.value = ''; });
            setMensagem(sucesso);
        } catch (e) { setErro(e); } finally { setOcupado(false); }
    }}>
        <MensagemErro erro={erro} />
        {mensagem && <p className="mensagem" role="status">{mensagem}</p>}
        {campos.map(({ nome, label, tipo = 'text', obrigatorio = true, ...props }) => <label key={nome}>{label}<input name={nome} type={tipo} defaultValue={valores[nome] ?? ''} required={obrigatorio} {...props} /></label>)}
        {children}
        <button className="botao-principal" disabled={ocupado || desabilitado}>{ocupado ? 'Processando…' : botao}</button>
    </form>;
}

export const senhaCampos = [
    { nome: 'password', label: 'Nova senha (15 a 64 caracteres)', tipo: 'password', minLength: 15, maxLength: 64, autoComplete: 'new-password' },
    { nome: 'password_confirmation', label: 'Confirme a senha', tipo: 'password', minLength: 15, maxLength: 64, autoComplete: 'new-password' },
];
