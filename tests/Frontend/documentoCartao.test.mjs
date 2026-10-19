import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatarDocumentoCartao, normalizarDocumentoCartao } from '../../resources/js/services/documentoCartao.js';

test('CPF e CNPJ seguem o tipo selecionado, inclusive durante a digitação', () => {
    assert.equal(formatarDocumentoCartao('CPF', '1234'), '123.4');
    assert.equal(formatarDocumentoCartao('CPF', '12345678901'), '123.456.789-01');
    assert.equal(formatarDocumentoCartao('CNPJ', '1234'), '12.34');
    assert.equal(formatarDocumentoCartao('CNPJ', '12345678901234'), '12.345.678/9012-34');
    assert.equal(formatarDocumentoCartao('CPF', ''), '');
});

test('colagem formatada é normalizada e limitada antes de chegar ao SDK', () => {
    assert.equal(normalizarDocumentoCartao('CPF', '123.456.789-01999'), '12345678901');
    assert.equal(normalizarDocumentoCartao('CNPJ', '12.345.678/9012-34999'), '12345678901234');
    assert.equal(formatarDocumentoCartao('CPF', 'abc123.456.789-01'), '123.456.789-01');
});

test('outros tipos de documento preservam letras e números', () => {
    assert.equal(formatarDocumentoCartao('PASS', 'AB123456'), 'AB123456');
    assert.equal(normalizarDocumentoCartao('PASS', 'AB123456'), 'AB123456');
});
