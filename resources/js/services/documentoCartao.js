export const formatosDocumentoCartao = {
    CPF: '000.000.000-00',
    CNPJ: '00.000.000/0000-00',
};

export function normalizarDocumentoCartao(tipo, valor) {
    const formato = formatosDocumentoCartao[tipo];
    return formato ? valor.replace(/\D/g, '').slice(0, tipo === 'CPF' ? 11 : 14) : valor;
}

export function formatarDocumentoCartao(tipo, valor) {
    const numero = normalizarDocumentoCartao(tipo, valor);
    const formato = formatosDocumentoCartao[tipo];
    if (!formato) return numero;
    let indice = 0;
    let resultado = '';
    for (const caractere of formato) {
        if (indice >= numero.length) break;
        resultado += caractere === '0' ? numero[indice++] : caractere;
    }
    return resultado;
}
