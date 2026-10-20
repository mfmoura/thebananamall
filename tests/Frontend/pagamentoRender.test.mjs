import test, { after } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter, Routes, Route } from 'react-router-dom';

// Renderiza a página real com respostas fictícias da API; não cria cobranças.
const pasta = await mkdtemp(resolve('node_modules/.pagamento-render-'));
after(() => rm(pasta, { recursive: true, force: true }));
const arquivo = join(pasta, 'pagina.mjs');
const resultado = await build({
    entryPoints: ['resources/js/pages/Pagamento.jsx'], bundle: true, write: false,
    platform: 'node', format: 'esm', packages: 'external', jsx: 'automatic',
    plugins: [{ name: 'respostas-homologacao', setup(builder) {
        builder.onResolve({ filter: /hooks\/useDados|context\/LojaContext|services\/api|components\/(Formulario|PagamentoCartao|BloqueioAutenticacao)$/ }, args => ({ path: args.path, namespace: 'fixture' }));
        builder.onLoad({ filter: /.*/, namespace: 'fixture' }, args => ({ contents:
            args.path.includes('useDados') ? 'export default function(){return {dados: globalThis.__pagamentoFixture};}'
                : args.path.includes('LojaContext') ? 'export function useLoja(){return {usuario:{email:"teste@example.com"}};}'
                    : args.path.includes('services/api') ? 'export const api = {};'
                        : args.path.includes('Formulario') ? 'export function MensagemErro(){return null;}'
                            : 'export default function(){return null;}', loader: 'js' }));
    } }],
});
await writeFile(arquivo, resultado.outputFiles[0].text);
const { default: Pagamento } = await import(pathToFileURL(arquivo));

function renderizar(meio, pagamento, status = 'aguardando_pagamento') {
    globalThis.__pagamentoFixture = { pedido: { id: 42, total: '50.00', status, forma_pagamento: { codigo: meio, nome: meio } }, pagamento };
    return renderToStaticMarkup(React.createElement(MemoryRouter, { initialEntries: ['/checkout/pagamento/42'] },
        React.createElement(Routes, null, React.createElement(Route, { path: '/checkout/pagamento/:id', element: React.createElement(Pagamento) }))));
}

test('prazo real mostra cronômetro e barra; confirmação remove ambos', () => {
    const agora = Date.now();
    const pagamento = { id: 1, created_at: new Date(agora - 300000).toISOString(), instrucoes: {
        criado_em: new Date(agora - 300000).toISOString(), expira_em: new Date(agora + 300000).toISOString(),
    } };
    const html = renderizar('pix', pagamento);
    assert.match(html, /Tempo restante para pagar/);
    assert.match(html, /role="timer"/);
    assert.match(html, /<progress/);
    assert.doesNotMatch(renderizar('pix', pagamento, 'pago'), /role="timer"|<progress/);
});

test('prazo esgotado não declara cancelamento e ausência de prazo exibe espera', () => {
    const pagamento = { id: 1, created_at: '2020-01-01T00:00:00Z', instrucoes: { criado_em: '2020-01-01T00:00:00Z', expira_em: '2020-01-01T00:10:00Z' } };
    const html = renderizar('boleto', pagamento);
    assert.match(html, /00:00:00/);
    assert.match(html, /prazo informado terminou/);
    assert.doesNotMatch(html, /Pedido cancelado/);
    const espera = renderizar('cartao_debito', { id: 1, created_at: pagamento.created_at });
    assert.match(espera, /Aguardando confirmação há/);
    assert.doesNotMatch(espera, /<progress/);
});

test('boleto exibe linha somente leitura, cópia e impressão HTTPS em outra aba', () => {
    const html = renderizar('boleto', { id: 1, instrucoes: { digitable_line: '23790.12345 60000.123456', ticket_url: 'https://example.com/boleto' } });
    assert.match(html, /23790\.12345 60000\.123456/);
    assert.match(html, /readOnly=""/);
    assert.match(html, /Copiar linha digitável/);
    assert.match(html, /href="https:\/\/example.com\/boleto" target="_blank" rel="noopener noreferrer"/);
    assert.match(html, /Abrir \/ imprimir boleto/);
    assert.match(html, /emissão do boleto não confirma o pagamento/);
});

test('Pix sem cobrança abre em preparação sem exigir ação para gerar', () => {
    const html = renderizar('pix', null);
    assert.match(html, /Preparando pagamento/);
    assert.match(html, /Estamos preparando o pagamento em segundo plano/);
    assert.doesNotMatch(html, /Gerar Pix|Retomar a mesma tentativa|Copiar código Pix/);
});

test('boleto rejeita link de impressão com protocolo inseguro', () => {
    for (const ticket_url of ['http://example.com', 'javascript:alert(1)', 'inválida']) {
        assert.doesNotMatch(renderizar('boleto', { id: 1, instrucoes: { ticket_url } }), /Abrir \/ imprimir boleto/);
    }
});

test('Pix exibe QR, copia e cola e orientação', () => {
    const html = renderizar('pix', { id: 1, instrucoes: { qr_code: 'pix-ficticio', qr_code_base64: 'aGVsbG8=' } });
    assert.match(html, /data:image\/png;base64,aGVsbG8=/);
    assert.match(html, /pix-ficticio/);
    assert.match(html, /Copiar código Pix/);
    assert.match(html, /Confira o valor e o destinatário/);
});

test('fila sem instruções mostra preparação e não oferece nova cobrança', () => {
    const html = renderizar('boleto', { id: 1, processamento: 'pendente' });
    assert.match(html, /Preparando pagamento/);
    assert.match(html, /preparando o pagamento em segundo plano/);
    assert.doesNotMatch(html, /Gerar boleto|Retomar a mesma tentativa|Pagamento confirmado/);
});

test('recusa e confirmação ocultam instruções antigas e botões de pagamento', () => {
    for (const [status, pedidoStatus, titulo] of [['failed', 'aguardando_pagamento', 'Pagamento recusado'], ['processed', 'pago', 'Pagamento confirmado'], ['expired', 'aguardando_pagamento', 'Pagamento expirado']]) {
        const html = renderizar('boleto', { id: 1, status, processamento: 'concluido', instrucoes: { digitable_line: 'linha-antiga', ticket_url: 'https://example.com' } }, pedidoStatus);
        assert.match(html, new RegExp(titulo));
        assert.doesNotMatch(html, /linha-antiga|Abrir \/ imprimir boleto|Gerar boleto|Atualizar pagamento/);
    }
});

test('verificação impede reutilização de instruções e orienta contato', () => {
    const html = renderizar('pix', { id: 1, processamento: 'verificacao_necessaria', instrucoes: { qr_code: 'codigo-antigo' } });
    assert.match(html, /Pagamento em verificação/);
    assert.match(html, /Entre em contato com a loja antes de tentar outro pagamento/);
    assert.doesNotMatch(html, /codigo-antigo|Copiar código Pix/);
});

for (const meio of ['pix', 'boleto', 'cartao_credito', 'cartao_debito']) {
    test(`${meio}: HTML de pendência não declara aprovação nem oferece outra cobrança`, () => {
        const html = renderizar(meio, { id: 1, order_id: 'order-ficticia', status: 'action_required', processamento: 'aguardando' });
        assert.match(html, /A confirmação depende do retorno do Mercado Pago/);
        assert.doesNotMatch(html, /Atualizar pagamento/);
        assert.doesNotMatch(html, /Pagamento confirmado|Gerar Pix|Gerar boleto|Retomar a mesma tentativa|Abra o aplicativo do seu banco|Use a linha digitável/);
    });

    test(`${meio}: HTML de recusa e confirmação remove instruções e preserva orientação`, () => {
        for (const [status, pedidoStatus, titulo, mensagem] of [
            ['failed', 'aguardando_pagamento', 'Pagamento recusado', 'Consulte a loja para continuar'],
            ['processed', 'pago', 'Pagamento confirmado', 'Recebemos a confirmação do pagamento'],
        ]) {
            const html = renderizar(meio, { id: 1, status, processamento: 'concluido', instrucoes: {
                qr_code: 'pix-antigo', digitable_line: 'linha-antiga', ticket_url: 'https://example.com/boleto-antigo',
            } }, pedidoStatus);
            assert.match(html, new RegExp(titulo));
            assert.match(html, new RegExp(mensagem));
            assert.doesNotMatch(html, /pix-antigo|linha-antiga|boleto-antigo|Atualizar pagamento|Retomar a mesma tentativa|Gerar Pix|Gerar boleto/);
        }
    });
}
