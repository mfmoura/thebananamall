import test from 'node:test';
import assert from 'node:assert/strict';
import { tempoPagamento, formatarTempoPagamento } from '../../resources/js/services/tempoPagamento.js';

const inicio = Date.parse('2026-10-19T18:00:00Z');
const pagamento = { instrucoes: { criado_em: '2026-10-19T18:00:00Z', expira_em: '2026-10-19T18:10:00Z' } };

test('barra esgota conforme o prazo absoluto, inclusive depois de reabrir a tela', () => {
    assert.deepEqual(tempoPagamento(pagamento, inicio), { restante: true, segundos: 600, percentual: 100 });
    assert.deepEqual(tempoPagamento(pagamento, inicio + 300000), { restante: true, segundos: 300, percentual: 50 });
    assert.deepEqual(tempoPagamento(pagamento, inicio + 700000), { restante: true, segundos: 0, percentual: 0 });
    assert.equal(tempoPagamento(pagamento, inicio - 1000).percentual, 100);
});

test('sem vencimento exibe tempo decorrido sem inventar prazo de pagamento', () => {
    assert.deepEqual(tempoPagamento({ created_at: '2026-10-19T18:00:00Z' }, inicio + 65000), { restante: false, segundos: 65 });
    assert.equal(tempoPagamento({}, inicio).segundos, null);
    assert.equal(tempoPagamento({ instrucoes: { criado_em: 'inválido', expira_em: 'inválido' } }, inicio).segundos, null);
});

test('vencimento inválido ou anterior à emissão não produz contagem falsa', () => {
    assert.equal(tempoPagamento({ instrucoes: { ...pagamento.instrucoes, expira_em: '2026-10-19T17:00:00Z' } }, inicio).restante, false);
    assert.equal(tempoPagamento({ instrucoes: { ...pagamento.instrucoes, expira_em: 'inválido' } }, inicio).restante, false);
});

test('formata horas, minutos e segundos, incluindo prazos maiores que um dia', () => {
    assert.equal(formatarTempoPagamento(0), '00:00:00');
    assert.equal(formatarTempoPagamento(65), '00:01:05');
    assert.equal(formatarTempoPagamento(90061), '25:01:01');
});
