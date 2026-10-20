const pagos = ['pago', 'em_processamento', 'enviado', 'entregue'];
const encerrados = ['failed', 'canceled', 'cancelled', 'expired', 'refunded', 'charged_back'];

export function estadoPagamento(pedido, pagamento, meio) {
    if (pedido?.status === 'cancelado') return { encerrado: true, titulo: 'Pedido cancelado', mensagem: 'Este pedido foi cancelado. Consulte a loja antes de realizar outro pagamento.' };
    if (pagamento?.status === 'refunded') return { encerrado: true, titulo: 'Pagamento estornado', mensagem: 'O pagamento foi estornado. Consulte os detalhes com a loja.' };
    if (pagamento?.status === 'charged_back') return { encerrado: true, titulo: 'Pagamento contestado', mensagem: 'O pagamento foi contestado. Entre em contato com a loja.' };
    if (pagos.includes(pedido?.status)) return { confirmado: true, titulo: 'Pagamento confirmado', mensagem: `Recebemos a confirmação do pagamento do pedido #${pedido.id}.` };
    if (pagamento?.status === 'failed') return { encerrado: true, titulo: 'Pagamento recusado', mensagem: 'O pagamento não foi aprovado. Consulte a loja para continuar; este pagamento não permite novas tentativas.' };
    if (pagamento?.status === 'expired') return { encerrado: true, titulo: 'Pagamento expirado', mensagem: 'O prazo deste pagamento terminou. Não utilize os códigos anteriores; entre em contato com a loja.' };
    if (encerrados.includes(pagamento?.status) || pagamento?.processamento === 'concluido') return { encerrado: true, titulo: 'Pagamento encerrado', mensagem: 'Este pagamento não está disponível para novas tentativas. Consulte o pedido ou entre em contato com a loja.' };
    if (pagamento?.processamento === 'verificacao_necessaria') return { verificacao: true, titulo: 'Pagamento em verificação' };
    const instrucoes = pagamento?.instrucoes || {};
    if (meio === 'pix' && (instrucoes.qr_code || instrucoes.qr_code_base64)) return { titulo: 'Aguardando pagamento por Pix' };
    if (meio === 'boleto' && (instrucoes.digitable_line || instrucoes.ticket_url)) return { titulo: 'Aguardando pagamento do boleto' };
    return { titulo: ['pix', 'boleto'].includes(meio) ? 'Preparando pagamento' : 'Acompanhamento do pagamento' };
}
