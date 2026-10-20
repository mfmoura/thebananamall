import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import Formulario, { MensagemErro, senhaCampos } from '../../components/Formulario';
import { api, sessao } from '../../services/api';

export default function ConfirmacaoEmail() {
    const { pathname, search } = useLocation();
    const navigate = useNavigate();
    const parametros = new URLSearchParams(search);
    const confirmar = pathname === '/dashboard/email/verify';
    const primeiraSenha = pathname === '/dashboard/senha-obrigatoria';
    const exigeSenha = parametros.get('requires_password') === '1';
    const token = parametros.get('token');
    const automatico = confirmar && !exigeSenha;
    const processamento = useRef(null);
    const [erro, setErro] = useState(null);

    useEffect(() => {
        if (!automatico) return;
        let ativo = true;
        setErro(null);
        if (!token) {
            setErro(new Error('Link inválido. Solicite um novo e-mail.'));
            return;
        }
        if (processamento.current?.token !== token) {
            processamento.current = { token, promessa: sessao('/dashboard/email/confirmar', { token }) };
        }
        processamento.current.promessa.then((resposta) => {
            if (ativo) navigate(resposta.requires_password_change ? '/dashboard/senha-obrigatoria' : '/dashboard/login', { replace: true });
        }).catch((falha) => { if (ativo) setErro(falha); });
        return () => { ativo = false; };
    }, [automatico, token, navigate]);
    const campos = primeiraSenha ? [
        { nome: 'login', label: 'Login ou e-mail', autoComplete: 'username' },
        { nome: 'current_password', label: 'Senha inicial', tipo: 'password', autoComplete: 'current-password' },
        ...senhaCampos,
    ] : confirmar ? (exigeSenha ? senhaCampos : []) : [{ nome: 'email', label: 'E-mail', tipo: 'email', autoComplete: 'email' }];

    return <main className="dashboard-login"><section className="tela tela-form">
        <p className="dashboard-marca">LCB · Administração</p>
        <h1>{primeiraSenha ? 'Trocar senha inicial' : confirmar ? 'Confirmar e-mail administrativo' : 'Solicitar confirmação'}</h1>
        <p>{primeiraSenha ? 'Escolha uma nova senha para liberar o acesso ao painel.' : automatico ? 'Validando seu link para continuar…' : confirmar ? 'Escolha uma nova senha para concluir a troca de e-mail.' : 'Informe seu e-mail para solicitar um novo link de confirmação do painel.'}</p>
        {automatico ? <>{erro ? <MensagemErro erro={erro} /> : <p role="status">Aguarde, confirmando seu e-mail…</p>}</> : <Formulario key={pathname} bloquearTela campos={campos} botao={primeiraSenha ? 'Trocar senha' : confirmar ? 'Salvar nova senha' : 'Reenviar confirmação'}
            sucesso="Se houver uma conta pendente, a solicitação de envio será enfileirada. Confira sua caixa de entrada e spam."
            enviar={async (dados) => {
                if (primeiraSenha) {
                    await sessao('/oauth/first-login/password', dados);
                    navigate('/dashboard/login', { replace: true });
                } else if (confirmar) {
                    if (!token) throw new Error('Link inválido. Solicite um novo e-mail.');
                    const resposta = await sessao('/dashboard/email/confirmar', { ...dados, token });
                    navigate(resposta.requires_password_change ? '/dashboard/senha-obrigatoria' : '/dashboard/login', { replace: true });
                } else await api.reenviarEmail(dados);
            }} />}
        {confirmar && <p><Link to="/dashboard/email/pendente">Solicitar novo link</Link></p>}
        <Link to="/dashboard/login">Voltar para o login administrativo</Link>
    </section></main>;
}
