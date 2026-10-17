import { useEffect, useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import Formulario, { MensagemErro, senhaCampos } from '../components/Formulario';
import { api, sessao } from '../services/api';
import { entrar, concluirOAuth } from '../services/oauth';
import { carrinhoApi } from '../services/carrinho';
import { useLoja } from '../context/LojaContext';

export function Login() {
    const navigate = useNavigate();
    return <section className="tela tela-form"><h1>Entrar</h1>
        <Formulario campos={[{ nome: 'login', label: 'Login ou e-mail', autoComplete: 'username' }, { nome: 'password', label: 'Senha', tipo: 'password', autoComplete: 'current-password' }]} botao="Entrar" sucesso="Autorizando acesso…" enviar={async (dados) => {
            try { await entrar(dados); } catch (erro) {
                if (erro.data?.code === 'password_change_required') navigate('/senha-obrigatoria');
                else if (erro.data?.code === 'email_confirmation_required') navigate('/email/pendente');
                throw erro;
            }
        }} />
        <p><Link to="/cadastro">Criar conta</Link> · <Link to="/senha/recuperar">Esqueci minha senha</Link></p>
    </section>;
}

export function Callback() {
    const { atualizarUsuario, atualizarCarrinho, setErro: avisar } = useLoja();
    const navigate = useNavigate();
    const [erro, setErro] = useState(null);
    useEffect(() => {
        let ativo = true;
        (async () => {
            await concluirOAuth();
            if (!ativo) return;
            const usuario = await atualizarUsuario();
            if (usuario.cliente) {
                try { await carrinhoApi.mesclar(); } catch (e) { avisar(`Você entrou, mas não foi possível mesclar o carrinho: ${e.message}`); }
            }
            await atualizarCarrinho();
            const destino = sessionStorage.getItem('lcb.destino');
            sessionStorage.removeItem('lcb.destino');
            navigate(['/checkout', '/conta', '/pedidos'].includes(destino) ? destino : '/conta', { replace: true });
        })().catch((e) => { if (ativo) setErro(e); });
        return () => { ativo = false; };
    }, []);
    return <section className="tela"><h1>Entrando na sua conta</h1><MensagemErro erro={erro} />{erro ? <Link to="/login">Tentar novamente</Link> : <p role="status">Concluindo acesso e atualizando seu carrinho…</p>}</section>;
}

export function Cadastro() {
    return <section className="tela tela-form"><h1>Criar conta</h1><Formulario campos={[{ nome: 'login', label: 'Nome de usuário', autoComplete: 'username' }, { nome: 'email', label: 'E-mail', tipo: 'email', autoComplete: 'email' }, ...senhaCampos]} enviar={api.cadastrar} botao="Cadastrar" sucesso="Conta criada. Confira seu e-mail para confirmar o cadastro." /><Link to="/login">Já tenho uma conta</Link></section>;
}

export function FluxoEmailSenha() {
    const { logout } = useLoja();
    const { pathname, search } = useLocation();
    const token = new URLSearchParams(search).get('token');
    const confirmar = pathname === '/email/verify';
    const exigeSenha = new URLSearchParams(search).get('requires_password') === '1';
    const redefinir = ['/senha/redefinir', '/password/reset'].includes(pathname);
    const primeira = pathname === '/senha-obrigatoria';
    const pendente = pathname === '/email/pendente';
    const titulo = confirmar ? 'Confirmar e-mail' : redefinir ? 'Redefinir senha' : primeira ? 'Trocar senha inicial' : pendente ? 'Confirmar cadastro' : 'Recuperar senha';
    const campos = primeira ? [{ nome: 'login', label: 'Login ou e-mail' }, { nome: 'current_password', label: 'Senha atual', tipo: 'password', autoComplete: 'current-password' }, ...senhaCampos] : redefinir || (confirmar && exigeSenha) ? senhaCampos : confirmar ? [] : [{ nome: 'email', label: 'E-mail', tipo: 'email' }];
    return <section className="tela tela-form"><h1>{titulo}</h1>
        {confirmar && <p>{exigeSenha ? 'Informe uma nova senha para concluir a troca de e-mail.' : 'Confirme seu cadastro para entrar na loja.'}</p>}
        <Formulario key={pathname} campos={campos} botao="Confirmar" sucesso={confirmar || redefinir || primeira ? 'Operação concluída. Entre novamente na sua conta.' : 'Se houver uma conta correspondente, enviaremos um link para o e-mail informado.'} enviar={async (dados) => {
            if (confirmar || redefinir) {
                if (!token) throw new Error('Link inválido. Solicite um novo e-mail.');
                if (!dados.password) { delete dados.password; delete dados.password_confirmation; }
                await (confirmar ? api.confirmarEmail : api.redefinirSenha)({ ...dados, token });
                await logout();
            } else if (primeira) await sessao('/oauth/first-login/password', dados);
            else await (pendente ? api.reenviarEmail : api.recuperarSenha)(dados);
        }} /><Link to="/login">Voltar para o login</Link>
    </section>;
}
