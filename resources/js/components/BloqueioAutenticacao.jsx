import { useLayoutEffect } from 'react';
import { useProcessamento } from '../context/ProcessamentoContext';

export default function BloqueioAutenticacao({ rotulo = 'Processando autenticação' }) {
    const { iniciar } = useProcessamento();
    useLayoutEffect(() => iniciar(rotulo), [iniciar, rotulo]);
    return null;
}
