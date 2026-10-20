import { useEffect, useRef, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import Formulario, { MensagemErro, senhaCampos } from '../components/Formulario';
import { api, sessao } from '../services/api';
import { retornoAposLogin } from '../services/navegacao';
import BloqueioAutenticacao from '../components/BloqueioAutenticacao';
import { useLoja } from '../context/LojaContext';

export function Login() {
    const navigate = useNavigate();
    const { login } = useLoja();
    return <section className="tela tela-form"><h1>Entrar</h1>
        <Formulario bloquearTela limparSenhasAposEnvio={false} action="/oauth/login" campos={[{ nome: 'login', id: 'loja-username', label: 'Login ou e-mail', autoComplete: 'username' }, { nome: 'password', id: 'loja-current-password', label: 'Senha', tipo: 'password', autoComplete: 'current-password' }]} botao="Entrar" textoOcupado="Entrar" sucesso="" enviar={async (dados) => {
            try { await login(dados); navigate(retornoAposLogin(), { replace: true }); } catch (erro) {
                if (erro.data?.code === 'password_change_required') navigate('/senha-obrigatoria');
                else if (erro.data?.code === 'email_confirmation_required') navigate('/email/pendente');
                throw erro;
            }
        }} />
        <p><Link to="/cadastro">Criar conta</Link> · <Link to="/senha/recuperar">Esqueci minha senha</Link></p>
    </section>;
}

export function Callback() {
    const { finalizarLogin } = useLoja();
    const navigate = useNavigate();
    const [erro, setErro] = useState(null);
    const processamento = useRef(null);
    useEffect(() => {
        let ativo = true;
        processamento.current ??= finalizarLogin();
        processamento.current.then(() => {
            if (ativo) navigate(retornoAposLogin(), { replace: true });
        }).catch((e) => { if (ativo) setErro(e); });
        return () => { ativo = false; };
    }, [finalizarLogin, navigate]);
    return erro ? <section className="tela"><MensagemErro erro={erro} /><Link to="/login">Tentar novamente</Link></section> : <BloqueioAutenticacao />;
}

export function Cadastro() {
    const navigate = useNavigate();
    return <section className="tela tela-form"><h1>Criar conta</h1><Formulario bloquearTela campos={[{ nome: 'login', label: 'Nome de usuário', autoComplete: 'username' }, { nome: 'email', label: 'E-mail', tipo: 'email', autoComplete: 'email' }, ...senhaCampos]} enviar={async (dados) => {
        await api.cadastrar(dados);
        navigate('/email/pendente', { replace: true, state: { cadastroConcluido: true, email: dados.email } });
    }} botao="Cadastrar" /><Link to="/login">Já tenho uma conta</Link></section>;
}

export function FluxoEmailSenha() {
    const { logout } = useLoja();
    const { pathname, search, state } = useLocation();
    const token = new URLSearchParams(search).get('token');
    const confirmar = pathname === '/email/verify';
    const exigeSenha = new URLSearchParams(search).get('requires_password') === '1';
    const redefinir = ['/senha/redefinir', '/password/reset'].includes(pathname);
    const primeira = pathname === '/senha-obrigatoria';
    const pendente = pathname === '/email/pendente';
    const titulo = confirmar ? 'Confirmar e-mail' : redefinir ? 'Redefinir senha' : primeira ? 'Trocar senha inicial' : pendente ? (state?.cadastroConcluido ? 'Cadastro realizado' : 'Confirmar cadastro') : 'Recuperar senha';
    const campos = primeira ? [{ nome: 'login', label: 'Login ou e-mail' }, { nome: 'current_password', label: 'Senha atual', tipo: 'password', autoComplete: 'current-password' }, ...senhaCampos] : redefinir || (confirmar && exigeSenha) ? senhaCampos : confirmar ? [] : [{ nome: 'email', label: 'E-mail', tipo: 'email' }];
    return <section className="tela tela-form"><h1>{titulo}</h1>
        {pendente && <p role="status">{state?.cadastroConcluido ? 'Sua conta foi criada. Confira sua caixa de entrada e clique no link enviado para confirmar seu e-mail antes de entrar.' : 'Confirme seu e-mail para entrar na loja.'} Se precisar de um novo link, informe seu e-mail abaixo.</p>}
        {confirmar && <p>{exigeSenha ? 'Informe uma nova senha para concluir a troca de e-mail.' : 'Confirme seu cadastro para entrar na loja.'}</p>}
        <Formulario bloquearTela key={pathname} campos={campos} valores={pendente ? { email: state?.email || '' } : {}} botao={pendente ? 'Reenviar confirmação' : 'Confirmar'} sucesso={confirmar || redefinir || primeira ? 'Operação concluída. Entre novamente na sua conta.' : 'Se houver uma conta correspondente, enviaremos um link para o e-mail informado.'} enviar={async (dados) => {
            if (confirmar || redefinir) {
                if (!token) throw new Error('Link inválido. Solicite um novo e-mail.');
                if (!dados.password) { delete dados.password; delete dados.password_confirmation; }
                await (confirmar ? api.confirmarEmail : api.redefinirSenha)({ ...dados, token });
                await logout();
            } else if (primeira) await sessao('/oauth/first-login/password', dados);
            else await (pendente ? api.reenviarEmail : api.recuperarSenha)(dados);
        }} />
        {(confirmar || redefinir) && <p>O link expirou? <Link to={confirmar ? '/email/pendente' : '/senha/recuperar'}>Solicitar novo e-mail</Link></p>}
        <Link to="/login">Voltar para o login</Link>
    </section>;
}
