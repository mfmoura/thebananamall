import test, { after } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import { pathToFileURL } from 'node:url';

const pasta = await mkdtemp(resolve('node_modules/.dashboard-'));
after(() => rm(pasta, { recursive: true, force: true }));
const arquivo = join(pasta, 'dashboard.mjs');
const bundle = await build({
    entryPoints: ['resources/js/services/dashboard.js'], bundle: true, write: false, format: 'esm', platform: 'node',
    plugins: [{ name: 'sessao-controlada', setup(builder) {
        builder.onResolve({ filter: /^\.\/api$/ }, () => ({ path: 'api', namespace: 'fixture' }));
        builder.onLoad({ filter: /.*/, namespace: 'fixture' }, () => ({ contents: `
            export const requisitar=(...args)=>globalThis.ambiente.requisitar(...args);
            export const sessao=async()=>({authorization_url:'/dashboard/oauth/authorize'});
        ` }));
    } }],
});
await writeFile(arquivo, bundle.outputFiles[0].text);
const { prepararDashboard } = await import(pathToFileURL(arquivo));

async function verificar({ path = '/dashboard/', ok = true, autorizada = true, existente = false } = {}, executar) {
    const anterior = { window: globalThis.window, fetch: globalThis.fetch };
    let consultas = 0;
    globalThis.window = { location: { origin: 'https://loja.example' } };
    globalThis.fetch = async () => ({ ok, url: new URL(path, window.location.origin).href });
    globalThis.ambiente = { requisitar: async (url) => {
        assert.equal(url, '/dashboard/session');
        consultas++;
        return { id: 1, api_autorizada: existente || (consultas > 1 && autorizada) };
    } };
    try { await executar(() => prepararDashboard(), () => consultas); }
    finally {
        globalThis.window = anterior.window;
        globalThis.fetch = anterior.fetch;
        delete globalThis.ambiente;
    }
}

for (const path of ['/dashboard', '/dashboard/']) {
    test(`autoriza o painel com retorno em ${path}`, () => verificar({ path }, async (preparar, consultas) => {
        assert.equal((await preparar()).api_autorizada, true);
        assert.equal(consultas(), 2);
    }));
}

for (const opcoes of [{ path: '/dashboard/login' }, { path: 'https://outro.example/dashboard/' }, { ok: false }, { autorizada: false }]) {
    test(`recusa autorização inválida: ${JSON.stringify(opcoes)}`, () => verificar(opcoes, async (preparar) => {
        await assert.rejects(preparar, /Não foi possível autorizar o painel/);
    }));
}

test('preserva sessão já autorizada', () => verificar({ existente: true }, async (preparar, consultas) => {
    assert.equal((await preparar()).api_autorizada, true);
    assert.equal(consultas(), 1);
}));
