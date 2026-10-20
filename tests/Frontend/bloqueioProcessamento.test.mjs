import test, { after } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const pasta = await mkdtemp(resolve('node_modules/.overlay-'));
after(() => rm(pasta, { recursive: true, force: true }));
const arquivo = join(pasta, 'overlay.mjs');
const bundle = await build({
    entryPoints: ['resources/js/components/BloqueioProcessamento.jsx'], bundle: true, write: false,
    platform: 'node', format: 'esm', packages: 'external', jsx: 'automatic',
    plugins: [{ name: 'ciclo-do-dialogo', setup(builder) {
        builder.onResolve({ filter: /^react$|^react-dom$/ }, args => ({ path: args.path, namespace: 'fixture' }));
        builder.onLoad({ filter: /.*/, namespace: 'fixture' }, args => ({ contents: args.path === 'react'
            ? 'export const useRef=()=>({current:globalThis.__overlay.dialogo}); export const useLayoutEffect=(callback)=>{globalThis.__overlay.montar=callback;};'
            : 'export const createPortal=(conteudo)=>conteudo;' }));
    } }],
});
await writeFile(arquivo, bundle.outputFiles[0].text);
const { default: Bloqueio } = await import(pathToFileURL(arquivo));

test('overlay entra na camada modal nativa e restaura foco, rolagem e estado inert ao sair', () => {
    const documentoAnterior = globalThis.document;
    const chamadas = [];
    const app = { inert: false };
    const anterior = { isConnected: true, focus: () => chamadas.push('foco anterior') };
    globalThis.document = { activeElement: anterior, body: { style: { overflow: 'auto' } }, getElementById: () => app };
    globalThis.__overlay = { dialogo: {
        showModal: () => chamadas.push('camada modal'), focus: () => chamadas.push('foco overlay'), close: () => chamadas.push('fechar'),
    } };
    try {
        const elemento = Bloqueio({ rotulo: 'Enviando pedido' });
        assert.equal(elemento.type, 'dialog');
        const desmontar = globalThis.__overlay.montar();
        assert.deepEqual(chamadas, ['camada modal', 'foco overlay']);
        assert.equal(app.inert, true);
        assert.equal(document.body.style.overflow, 'hidden');
        desmontar();
        assert.deepEqual(chamadas, ['camada modal', 'foco overlay', 'fechar', 'foco anterior']);
        assert.equal(app.inert, false);
        assert.equal(document.body.style.overflow, 'auto');
    } finally {
        globalThis.document = documentoAnterior;
        delete globalThis.__overlay;
    }
});

test('Escape e Tab não permitem dispensar o bloqueio ou mover foco para o modal de baixo', () => {
    const documentoAnterior = globalThis.document;
    globalThis.document = { body: {} };
    globalThis.__overlay = { dialogo: {} };
    try {
        const elemento = Bloqueio({});
        let cancelamentos = 0;
        elemento.props.onCancel({ preventDefault: () => cancelamentos++ });
        elemento.props.onKeyDown({ key: 'Tab', preventDefault: () => cancelamentos++ });
        assert.equal(cancelamentos, 2);
    } finally {
        globalThis.document = documentoAnterior;
        delete globalThis.__overlay;
    }
});
