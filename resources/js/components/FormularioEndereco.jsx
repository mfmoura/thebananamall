import Formulario from './Formulario';
import useAutocompleteCep from '../hooks/useAutocompleteCep';

const campos = ['cep', 'logradouro', 'numero', 'complemento', 'cidade', 'estado'].map((nome) => ({
    nome, label: ({ numero: 'Número', estado: 'UF', cep: 'CEP' })[nome] || nome[0].toUpperCase() + nome.slice(1),
    obrigatorio: nome !== 'complemento',
    ...(nome === 'estado' ? { maxLength: 2 } : {}),
    ...(nome === 'cep' ? { inputMode: 'numeric', autoComplete: 'postal-code', maxLength: 9, pattern: '[0-9]{5}-?[0-9]{3}' } : {}),
}));

export default function FormularioEndereco({ valores, enviar }) {
    const { aoAlterarCampo, mensagem } = useAutocompleteCep();
    return <Formulario campos={campos} valores={valores} enviar={enviar} aoAlterarCampo={aoAlterarCampo}>
        {mensagem && <p role="status">{mensagem}</p>}
    </Formulario>;
}
