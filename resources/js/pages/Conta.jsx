import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useLoja } from '../context/LojaContext';
import { api } from '../services/api';
import FormularioEndereco from '../components/FormularioEndereco';
import Formulario, { MensagemErro, senhaCampos } from '../components/Formulario';


export default function Conta() {
    const { usuario, atualizarUsuario, logout } = useLoja();
    const [endereco, setEndereco] = useState(null);
    const [erro, setErro] = useState(null);
    const navigate = useNavigate();
    return <section className="tela tela-form"><h1>Minha conta</h1><p>{usuario.login} · {usuario.email}</p><MensagemErro erro={erro} />
        <h2>Nome de usuário</h2><Formulario key={usuario.login} campos={[{ nome: 'login', label: 'Login' }]} valores={usuario} enviar={async (dados) => { await api.atualizarUsuario(usuario.id, dados); await atualizarUsuario(); }} />
        <h2>Trocar senha</h2><Formulario campos={senhaCampos} enviar={async (dados) => { await api.atualizarUsuario(usuario.id, dados); await logout(); navigate('/login'); }} sucesso="Entre novamente com a nova senha." />
        <h2>Trocar e-mail</h2><Formulario campos={[{ nome: 'email', label: 'Novo e-mail', tipo: 'email' }]} enviar={({ email }) => api.trocarEmail(usuario.id, email)} sucesso="Confira o novo e-mail e confirme pelo link recebido." />
        <h2>Meus endereços</h2>
        {usuario.enderecos?.map((item) => <article className="produto-card" key={item.id}><p>{item.logradouro}, {item.numero} — {item.cidade}/{item.estado} · {item.cep}</p><button onClick={() => setEndereco(item)}>Editar</button><button onClick={async () => {
            if (!window.confirm('Excluir este endereço?')) return;
            try { await api.excluirEndereco(item.id); await atualizarUsuario(); } catch (e) { setErro(e); }
        }}>Excluir</button></article>)}
        <h3>{endereco ? 'Editar endereço' : 'Cadastrar endereço'}</h3>
        <FormularioEndereco key={endereco?.id || 'novo'} valores={endereco || {}} enviar={async (dados) => {
            await api.endereco({ ...dados, estado: dados.estado.toUpperCase(), pais: 'BR' }, endereco?.id);
            setEndereco(null); await atualizarUsuario();
        }} />
        {endereco && <button onClick={() => setEndereco(null)}>Cancelar edição</button>}
        <h2>Encerrar conta</h2><button onClick={async () => {
            if (!window.confirm('Excluir sua conta? Esta ação não pode ser desfeita.')) return;
            try {
                if (usuario.cliente) await api.excluirCliente(usuario.cliente.id);
                else await api.excluirUsuario(usuario.id);
                await logout(); navigate('/');
            } catch (e) { setErro(e); }
        }}>Excluir minha conta</button>
    </section>;
}
