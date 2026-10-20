import { useId, useState } from 'react';

export default function CampoSenha({ label, id: idInformado, ...props }) {
    const idGerado = useId();
    const id = idInformado || idGerado;
    const [visivel, setVisivel] = useState(false);

    return <div className="campo-senha">
        <label htmlFor={id}>{label}</label>
        <div className="campo-senha-controle">
            <input {...props} id={id} type={visivel ? 'text' : 'password'} data-campo-senha />
            <button className="senha-visibilidade" type="button" aria-controls={id}
                aria-label={`${visivel ? 'Ocultar' : 'Mostrar'} senha: ${label}`}
                title={visivel ? 'Ocultar senha' : 'Mostrar senha'}
                onClick={() => setVisivel((valor) => !valor)}>
                <i className={`fa-solid ${visivel ? 'fa-eye-slash' : 'fa-eye'}`} aria-hidden="true" />
            </button>
        </div>
    </div>;
}
