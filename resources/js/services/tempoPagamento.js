export function tempoPagamento(pagamento, agora = Date.now()) {
    const inicio = Date.parse(pagamento?.instrucoes?.criado_em || pagamento?.created_at || '');
    const fim = Date.parse(pagamento?.instrucoes?.expira_em || '');
    if (Number.isFinite(fim) && Number.isFinite(inicio) && fim > inicio) {
        return {
            restante: true,
            segundos: Math.max(0, Math.ceil((fim - agora) / 1000)),
            percentual: Math.max(0, Math.min(100, ((fim - agora) / (fim - inicio)) * 100)),
        };
    }
    return { restante: false, segundos: Number.isFinite(inicio) ? Math.max(0, Math.floor((agora - inicio) / 1000)) : null };
}

export function formatarTempoPagamento(segundos) {
    const horas = Math.floor(segundos / 3600);
    const minutos = Math.floor((segundos % 3600) / 60);
    return [horas, minutos, segundos % 60].map((parte) => String(parte).padStart(2, '0')).join(':');
}
