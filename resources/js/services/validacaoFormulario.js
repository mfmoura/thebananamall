export function mensagemValidacao(campo) {
    const validade = campo.validity;
    if (validade.valueMissing) return campo.tagName === 'SELECT' ? 'Selecione uma opção.' : 'Preencha este campo.';
    if (validade.typeMismatch) return campo.type === 'email' ? 'Informe um e-mail válido.' : 'Informe um endereço válido.';
    if (validade.tooShort) return `Use pelo menos ${campo.minLength} caracteres.`;
    if (validade.tooLong) return `Use no máximo ${campo.maxLength} caracteres.`;
    if (validade.rangeUnderflow) return `Informe um valor maior ou igual a ${campo.min}.`;
    if (validade.rangeOverflow) return `Informe um valor menor ou igual a ${campo.max}.`;
    if (validade.badInput) return 'Informe um número válido.';
    if (validade.stepMismatch) return 'Informe um valor compatível com o intervalo permitido.';
    if (validade.patternMismatch) return 'Confira o formato solicitado para este campo.';
    return 'Confira o valor informado neste campo.';
}
