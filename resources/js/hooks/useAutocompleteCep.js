import { useEffect, useRef, useState } from 'react';
import { api } from '../services/api';

export default function useAutocompleteCep() {
    const [mensagem, setMensagem] = useState('');
    const pendente = useRef({ timer: null, controller: null });
    const cancelar = () => {
        clearTimeout(pendente.current.timer);
        pendente.current.controller?.abort();
    };
    useEffect(() => cancelar, []);

    const aoAlterarCampo = (nome, valor, form) => {
        if (nome !== 'cep') return;
        cancelar();
        setMensagem('');
        const cep = valor.replace(/\D/g, '');
        if (!/^\d{8}$/.test(cep)) return;
        const controller = new AbortController();
        pendente.current.controller = controller;
        const campos = ['logradouro', 'cidade', 'estado'];
        const anteriores = Object.fromEntries(campos.map((campo) => [campo, form.elements.namedItem(campo)?.value]));
        setMensagem('Consultando CEP…');
        pendente.current.timer = setTimeout(async () => {
            try {
                const dados = await api.consultarCep(cep, controller.signal);
                if (controller.signal.aborted || !form.isConnected) return;
                for (const campo of campos) {
                    const input = form.elements.namedItem(campo);
                    if (input && input.value === anteriores[campo]) {
                        input.value = dados[campo] || '';
                        input.setCustomValidity('');
                    }
                }
                setMensagem('Endereço encontrado. Confira os dados e informe o número e o complemento.');
            } catch (erro) {
                if (!controller.signal.aborted) setMensagem(erro.data?.errors?.cep?.[0] || erro.message);
            }
        }, 400);
    };

    return { aoAlterarCampo, mensagem };
}
