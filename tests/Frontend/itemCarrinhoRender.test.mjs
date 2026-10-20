import test, { after } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';

const pasta = await mkdtemp(resolve('node_modules/.carrinho-render-'));
after(() => rm(pasta, { recursive: true, force: true }));
const arquivo = join(pasta, 'item.mjs');
const resultado = await build({
    entryPoints: ['resources/js/components/ItemCarrinho.jsx'], bundle: true, write: false,
    platform: 'node', format: 'esm', packages: 'external', jsx: 'automatic',
    plugins: [{ name: 'carrinho-fixture', setup(builder) {
        builder.onResolve({ filter: /context\/LojaContext$|services\/carrinho$/ }, args => ({ path: args.path, namespace: 'fixture' }));
        builder.onLoad({ filter: /.*/, namespace: 'fixture' }, args => ({
            contents: args.path.includes('services/carrinho') ? 'export const carrinhoApi = {};'
                : 'export function useLoja(){return {carrinhoOcupado:false,executarCarrinho:async()=>{}};}', loader: 'js',
        }));
    } }],
});
await writeFile(arquivo, resultado.outputFiles[0].text);
const { default: ItemCarrinho } = await import(pathToFileURL(arquivo));
const renderizar = (modificado, precoAtual = '25.00') => renderToStaticMarkup(
    React.createElement(MemoryRouter, null, React.createElement(ItemCarrinho, {
        item: { codigo: 1, nome: 'Camiseta', quantidade: 2, preco: '20.00', preco_atual: precoAtual, modificado },
    })),
);

test('alerta na linha mostra preço anterior e atual tanto no aumento quanto na redução', () => {
    for (const preco of ['25.00', '15.00']) {
        const html = renderizar(true, preco);
        assert.match(html, /role="alert"/);
        assert.match(html, /O preço por unidade mudou de R\$ 20,00 para R\$ (?:25|15),00/);
        assert.match(html, /Confira antes de finalizar/);
    }
});

test('preço sem divergência não exibe alerta', () => {
    assert.doesNotMatch(renderizar(false, '20.00'), /O preço por unidade mudou/);
});
