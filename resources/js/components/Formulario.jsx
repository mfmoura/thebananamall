import { useState } from 'react';
import { mensagemValidacao } from '../services/validacaoFormulario';
import CampoSenha from './CampoSenha';

export function MensagemErro({ erro }) {
    if (!erro) return null;
    return <div className="mensagem erro" role="alert"><p>{erro.message || erro}</p>{erro.data?.errors && <ul>{Object.entries(erro.data.errors).flatMap(([campo, mensagens]) => mensagens.map((mensagem, i) => <li key={`${campo}-${i}`}>{mensagem}</li>))}</ul>}</div>;
}

function aplicarMascara(mascara, valor) {
    const digitos = valor.replace(/\D/g, '');
    if (mascara === 'cep') return digitos.slice(0, 8).replace(/^(\d{5})(\d)/, '$1-$2');
    if (mascara === 'cpf-cnpj') {
        const numero = digitos.slice(0, 14);
        if (numero.length <= 11) return numero.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2');
        return numero.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }
    return valor;
}

function semMascara(mascara, valor) {
    return mascara ? valor.replace(/\D/g, '') : valor;
}

export default function Formulario({ campos, enviar, botao = 'Salvar', valores = {}, sucesso = 'Dados salvos.', children, desabilitado = false, aoAlterarCampo, textoOcupado = 'Processando…' }) {
    const [erro, setErro] = useState(null);
    const [mensagem, setMensagem] = useState('');
    const [ocupado, setOcupado] = useState(false);
    return <form className="formulario" onInvalid={(event) => event.target.setCustomValidity(mensagemValidacao(event.target))} onInput={(event) => event.target.setCustomValidity?.('')} onChange={(event) => {
        event.target.setCustomValidity?.('');
        const campo = campos.find(({ nome }) => nome === event.target.name);
        if (campo?.mascara) event.target.value = aplicarMascara(campo.mascara, event.target.value);
        aoAlterarCampo?.(event.target.name, event.target.value, event.currentTarget);
    }} onSubmit={async (event) => {
        event.preventDefault();
        if (ocupado || desabilitado) return;
        const form = event.currentTarget;
        setOcupado(true); setErro(null); setMensagem('');
        try {
            const dados = Object.fromEntries(new FormData(form));
            for (const campo of campos) if (campo.mascara && campo.nome in dados) dados[campo.nome] = semMascara(campo.mascara, dados[campo.nome]);
            await enviar(dados);
            form.querySelectorAll('input[data-campo-senha]').forEach((input) => { input.value = ''; });
            setMensagem(sucesso);
        } catch (e) { setErro(e); } finally { setOcupado(false); }
    }}>
        <MensagemErro erro={erro} />
        {mensagem && <p className="mensagem" role="status">{mensagem}</p>}
        {campos.map(({ nome, label, tipo = 'text', obrigatorio = true, mascara, ...props }) => tipo === 'password'
            ? <CampoSenha key={nome} label={label} name={nome} defaultValue={valores[nome] ?? ''} required={obrigatorio} {...props} />
            : tipo === 'checkbox'
                ? <label key={nome} className="campo-checkbox"><input name={nome} type="checkbox" required={obrigatorio} {...props} />{label}</label>
                : <label key={nome}>{label}<input name={nome} type={tipo} defaultValue={valores[nome] ?? ''} required={obrigatorio} {...props} /></label>)}
        {children}
        <button className="botao-principal" disabled={ocupado || desabilitado}>{ocupado ? textoOcupado : botao}</button>
    </form>;
}

export const senhaCampos = [
    { nome: 'password', label: 'Nova senha (8 a 15 caracteres)', tipo: 'password', minLength: 8, maxLength: 15, autoComplete: 'new-password' },
    { nome: 'password_confirmation', label: 'Confirme a senha', tipo: 'password', minLength: 8, maxLength: 15, autoComplete: 'new-password' },
];
