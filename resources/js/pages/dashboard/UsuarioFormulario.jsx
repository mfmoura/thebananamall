import { useState } from 'react';
import { Link, useNavigate, useOutletContext, useParams } from 'react-router-dom';
import Formulario, { MensagemErro, senhaCampos } from '../../components/Formulario';
import BloqueioAutenticacao from '../../components/BloqueioAutenticacao';
import { dashboardApi } from '../../services/dashboard';
import { sessao } from '../../services/api';
import useDados from '../../hooks/useDados';
import { paginasUsuario, tiposUsuario, podeGerenciarUsuario } from './Usuarios';

function DadosUsuario({ usuario, conta }) {
    const navigate = useNavigate();
    const [tipo, setTipo] = useState('cliente');
    const [paginas, setPaginas] = useState(usuario?.administrador?.paginas || []);
    const [mensagem, setMensagem] = useState('');
    const editando = Boolean(usuario);
    const proprio = usuario?.id === conta.id;
    const autorizado = !usuario || podeGerenciarUsuario(usuario, conta);
    const admin = editando ? Boolean(usuario.administrador) : tipo === 'administrador';
    const logistica = editando ? Boolean(usuario.logistica) : tipo === 'logistica';
    const encerrar = async () => { await sessao('/dashboard/logout'); navigate('/dashboard/login', { replace: true }); };
    return <div className="dashboard-editor"><Link to="/dashboard/usuarios">← Voltar para usuários</Link><h1>{editando ? 'Editar usuário' : 'Novo usuário'}</h1>
        {mensagem && <p className="mensagem" role="status">{mensagem}</p>}
        {!autorizado && <p role="alert">Você pode consultar esta conta, mas não alterar usuários com permissões que não possui.</p>}
        <section className="dashboard-secao"><h2>Dados da conta</h2>
            {editando && <p>E-mail: {usuario.email} · {usuario.email_validado ? 'Confirmado' : 'Pendente de confirmação'}</p>}
            <Formulario bloquearTela desabilitado={!autorizado} campos={[
                { nome: 'login', label: 'Login', maxLength: 255, autoComplete: 'off' },
                ...(!editando ? [{ nome: 'email', label: 'E-mail', tipo: 'email', maxLength: 255 }, ...senhaCampos.map((campo) => ({ ...campo, label: campo.nome === 'password' ? 'Senha inicial (8 a 15 caracteres)' : 'Confirme a senha' }))] : []),
                ...(logistica ? [{ nome: 'nome', label: 'Nome da logística', maxLength: 255 }, { nome: 'webhook', label: 'Webhook', tipo: 'url', maxLength: 2048 }] : []),
            ]} valores={{ ...usuario, ...usuario?.logistica }} botao={editando ? 'Salvar alterações' : 'Cadastrar usuário'} sucesso="" enviar={async (dados) => {
                const payload = { ...dados, ...(admin ? { paginas } : {}) };
                if (editando) {
                    await dashboardApi.atualizarUsuario(usuario.id, payload);
                    const alterouPermissoes = admin && [...paginas].sort().join(',') !== [...usuario.administrador.paginas].sort().join(',');
                    if (proprio && alterouPermissoes) { await encerrar(); return; }
                    setMensagem('Usuário atualizado.');
                } else {
                    const criado = await dashboardApi.criarUsuario({ ...payload, tipo });
                    navigate(`/dashboard/usuarios/${criado.id}/editar`, { replace: true, state: { criado: true } });
                }
            }}>
                {!editando ? <label>Perfil<select value={tipo} onChange={(event) => setTipo(event.target.value)}>{Object.entries(tiposUsuario).map(([valor, label]) => <option key={valor} value={valor}>{label}</option>)}</select></label>
                    : <p>Perfil: {Object.entries(tiposUsuario).filter(([perfil]) => usuario[perfil]).map(([, label]) => label).join(', ') || 'Sem perfil'}</p>}
                {admin && <fieldset disabled={!autorizado}><legend>Permissões administrativas</legend>{Object.entries(paginasUsuario).map(([valor, label]) => <label key={valor} className="campo-checkbox"><input type="checkbox" checked={paginas.includes(valor)} disabled={!conta.paginas.includes(valor) || (proprio && valor === 'usuarios')} onChange={(event) => setPaginas((atuais) => event.target.checked ? [...atuais, valor] : atuais.filter((item) => item !== valor))} />{label}</label>)}</fieldset>}
                {!editando && <p>O usuário precisará confirmar o e-mail para entrar. A solicitação de envio será enfileirada.</p>}
            </Formulario>
        </section>
        {editando && <><section className="dashboard-secao"><h2>Trocar senha</h2><p>A alteração encerra os acessos existentes e preserva o histórico de senhas.</p>
            <Formulario bloquearTela desabilitado={!autorizado} campos={senhaCampos} sucesso="Senha alterada. O usuário deverá entrar novamente." enviar={async (dados) => {
                await dashboardApi.atualizarUsuario(usuario.id, dados);
                if (proprio) await encerrar();
            }} />
        </section><section className="dashboard-secao"><h2>Trocar e-mail</h2><p>O novo endereço só será aplicado após confirmação pelo titular e definição de uma nova senha.</p>
            <Formulario bloquearTela desabilitado={!autorizado} campos={[{ nome: 'email', label: 'Novo e-mail', tipo: 'email', maxLength: 255 }]} botao="Solicitar troca" sucesso="Solicitação aceita. A confirmação será enviada pela fila de e-mails." enviar={(dados) => dashboardApi.trocarEmailUsuario(usuario.id, dados)} />
        </section></>}
    </div>;
}

export default function UsuarioFormulario() {
    const { id } = useParams();
    const conta = useOutletContext();
    const { dados, erro, carregando } = useDados(() => id ? dashboardApi.usuario(id) : Promise.resolve(null), id);
    if (carregando) return <BloqueioAutenticacao rotulo="Carregando usuário" />;
    if (erro) return <><MensagemErro erro={erro} /><Link to="/dashboard/usuarios">Voltar para usuários</Link></>;
    return <DadosUsuario key={id || 'novo'} usuario={dados} conta={conta} />;
}
