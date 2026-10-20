import test from 'node:test';
import assert from 'node:assert/strict';
import { estadoPagamento } from '../../resources/js/services/estadoPagamento.js';

const pedido = { id: 42, status: 'aguardando_pagamento' };

for (const meio of ['pix', 'boleto', 'credit_card', 'debit_card']) {
    test(`${meio}: pendência não confirma pagamento`, () => {
        const visual = estadoPagamento(pedido, { status: 'action_required', processamento: 'aguardando' }, meio);
        assert.ok(!visual.confirmado && !visual.encerrado);
        assert.equal(visual.titulo, ['pix', 'boleto'].includes(meio) ? 'Preparando pagamento' : 'Acompanhamento do pagamento');
    });
    test(`${meio}: recusa explícita encerra tentativa`, () => {
        const visual = estadoPagamento(pedido, { status: 'failed', processamento: 'concluido' }, meio);
        assert.equal(visual.titulo, 'Pagamento recusado');
        assert.equal(visual.encerrado, true);
        assert.ok(!visual.confirmado);
    });
    test(`${meio}: confirmação depende do pedido consultado na API`, () => {
        assert.ok(!estadoPagamento(pedido, { status: 'processed', processamento: 'concluido' }, meio).confirmado);
        assert.equal(estadoPagamento({ ...pedido, status: 'pago' }, { status: 'processed' }, meio).confirmado, true);
    });
}

test('Pix e boleto emitidos exibem espera de pagamento', () => {
    assert.equal(estadoPagamento(pedido, { instrucoes: { qr_code: 'pix-teste' } }, 'pix').titulo, 'Aguardando pagamento por Pix');
    assert.equal(estadoPagamento(pedido, { instrucoes: { digitable_line: 'linha-teste' } }, 'boleto').titulo, 'Aguardando pagamento do boleto');
});

test('verificação prevalece sobre instruções antigas', () => {
    const visual = estadoPagamento(pedido, { processamento: 'verificacao_necessaria', instrucoes: { digitable_line: 'antiga' } }, 'boleto');
    assert.equal(visual.verificacao, true);
    assert.equal(visual.titulo, 'Pagamento em verificação');
});

test('expiração, cancelamento, estorno e contestação possuem estados próprios', () => {
    assert.equal(estadoPagamento(pedido, { status: 'expired' }).titulo, 'Pagamento expirado');
    assert.equal(estadoPagamento({ ...pedido, status: 'cancelado' }, null).titulo, 'Pedido cancelado');
    for (const [status, titulo] of [['refunded', 'Pagamento estornado'], ['charged_back', 'Pagamento contestado']]) {
        const visual = estadoPagamento({ ...pedido, status: 'pago' }, { status });
        assert.equal(visual.titulo, titulo);
        assert.ok(visual.encerrado && !visual.confirmado);
    }
});
