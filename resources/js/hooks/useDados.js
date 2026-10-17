import { useEffect, useState } from 'react';

export default function useDados(carregar, dependencia) {
    const [estado, setEstado] = useState({ dados: null, erro: null, carregando: true });
    useEffect(() => {
        let ativo = true;
        setEstado({ dados: null, erro: null, carregando: true });
        carregar().then((dados) => { if (ativo) setEstado({ dados, erro: null, carregando: false }); })
            .catch((erro) => { if (ativo) setEstado({ dados: null, erro, carregando: false }); });
        return () => { ativo = false; };
    }, [dependencia]);
    return estado;
}
