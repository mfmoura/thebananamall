import test, { after } from 'node:test';
import assert from 'node:assert/strict';
import { build } from 'esbuild';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { resolve, join } from 'node:path';
import { pathToFileURL } from 'node:url';

const pasta = await mkdtemp(resolve('node_modules/.cartao-hook-'));
after(() => rm(pasta, { recursive: true, force: true }));
const arquivo = join(pasta, 'hook.mjs');
const bundle = await build({
    entryPoints: ['resources/js/hooks/useCartaoMercadoPago.js'], bundle: true, write: false, format: 'esm', platform: 'node',
    plugins: [{ name: 'ambiente-controlado', setup(builder) {
        builder.onResolve({ filter: /^react$|services\/mercadoPago$/ }, args => ({ path: args.path, namespace: 'fixture' }));
        builder.onLoad({ filter: /.*/, namespace: 'fixture' }, args => ({ contents: args.path === 'react'
            ? 'export const useState=(...a)=>globalThis.ambiente.useState(...a); export const useRef=(...a)=>globalThis.ambiente.useRef(...a); export const useEffect=(...a)=>globalThis.ambiente.useEffect(...a);'
            : 'export const carregarMercadoPago=()=>globalThis.ambiente.carregar();' }));
    } }],
});
await writeFile(arquivo, bundle.outputFiles[0].text);
const { default: useCartao } = await import(pathToFileURL(arquivo));

function preparar({ sincrono = false, incompleto = false, falhaSdk = false, semDocumentos = false } = {}) {
    const slots = [], timers = new Map(), instancias = [];
    let cursor = 0, dependencias, efeito, limpar, enviados = 0;
    const ambiente = {
        useState(inicial) {
            const i = cursor++;
            if (!(i in slots)) slots[i] = inicial;
            return [slots[i], valor => { slots[i] = typeof valor === 'function' ? valor(slots[i]) : valor; }];
        },
        useRef(inicial) { const i = cursor++; return slots[i] ??= { current: inicial }; },
        useEffect(callback, deps) {
            if (!dependencias || deps.some((valor, i) => valor !== dependencias[i])) {
                dependencias = deps; efeito = callback;
            }
        },
        carregar: () => falhaSdk ? Promise.reject(new Error('rede')) : Promise.resolve(class {
            cardForm({ callbacks, iframe }) {
                assert.equal(iframe, true);
                const instancia = { callbacks, desmontagens: 0, unmount() { this.desmontagens++; }, getCardFormData: () => ({ token: 'temporario', paymentMethodId: 'debelo' }) };
                instancias.push(instancia);
                if (sincrono) callbacks.onReady();
                return instancia;
            }
        }),
    };
    globalThis.ambiente = ambiente;
    const clearOriginal = globalThis.clearTimeout;
    globalThis.clearTimeout = id => timers.delete(id);
    globalThis.window = { setTimeout(callback, ms) { const id = Symbol(); timers.set(id, { callback, ms }); return id; } };
    globalThis.document = { getElementById(id) {
        if (incompleto && id === 'mp-cvv') return null;
        if (id === 'mp-documento-tipo') return { options: semDocumentos ? [{ value: '' }] : [{ value: 'CPF' }, { value: 'CNPJ' }] };
        return { querySelectorAll: () => [{}], getBoundingClientRect: () => ({ width: 100, height: 48 }), getClientRects: () => [{}] };
    } };
    globalThis.getComputedStyle = () => ({ pointerEvents: 'auto' });
    const render = () => {
        cursor = 0;
        const estado = useCartao({ publicKey: 'publica-teste', total: '50.00', email: 'teste@example.com', aoTokenizar: async () => { enviados++; } });
        if (efeito) { limpar?.(); limpar = efeito(); efeito = null; }
        return estado;
    };
    return { render, timers, instancias, enviados: () => enviados, async iniciar() { render(); await Promise.resolve(); await Promise.resolve(); }, encerrar() { limpar?.(); globalThis.clearTimeout = clearOriginal; delete globalThis.window; delete globalThis.document; delete globalThis.getComputedStyle; delete globalThis.ambiente; } };
}

test('onReady síncrono cancela o timeout e libera campos completos', async () => {
    const a = preparar({ sincrono: true });
    try { await a.iniciar(); assert.equal(a.render().pronto, true); assert.equal(a.timers.size, 0); }
    finally { a.encerrar(); }
});

test('carregamento acima de 15 segundos ainda pode concluir antes de 30 segundos', async () => {
    const a = preparar();
    try {
        await a.iniciar();
        assert.equal([...a.timers.values()][0].ms, 30000);
        a.instancias[0].callbacks.onReady();
        assert.equal(a.render().pronto, true);
        assert.equal(a.timers.size, 0);
    } finally { a.encerrar(); }
});

test('timeout desmonta campos e permite nova montagem sem enviar pagamento', async () => {
    const a = preparar();
    try {
        await a.iniciar();
        const antiga = a.instancias[0];
        [...a.timers.values()][0].callback();
        assert.equal(antiga.desmontagens, 1);
        assert.equal(a.render().falhaCarregamento, true);
        antiga.callbacks.onReady();
        await antiga.callbacks.onSubmit({ preventDefault() {} });
        assert.equal(a.render().pronto, false);
        assert.equal(a.enviados(), 0);
        a.render().recarregar(); a.render(); await Promise.resolve();
        assert.equal(a.instancias.length, 2);
        a.instancias[1].callbacks.onReady();
        assert.equal(a.render().pronto, true);
        assert.equal(a.render().erro, null);
    } finally { a.encerrar(); }
});

test('iframe incompleto mantém pagamento bloqueado e permite recuperação', async () => {
    const a = preparar({ incompleto: true });
    try {
        await a.iniciar(); a.instancias[0].callbacks.onReady();
        assert.equal(a.render().pronto, false);
        assert.equal(a.render().falhaCarregamento, true);
        assert.equal(a.timers.size, 0);
    } finally { a.encerrar(); }
});

test('falha ao buscar documentos encerra espera com mensagem específica', async () => {
    const a = preparar();
    try {
        await a.iniciar(); a.instancias[0].callbacks.onIdentificationTypesReceived(new Error('rede'));
        assert.match(a.render().erro.message, /tipos de documento/);
        assert.equal(a.timers.size, 0);
        assert.equal(a.render().pronto, false);
    } finally { a.encerrar(); }
});

test('falha no script oferece recuperação', async () => {
    const a = preparar({ falhaSdk: true });
    try { await a.iniciar(); assert.equal(a.render().falhaCarregamento, true); }
    finally { a.encerrar(); }
});

test('seletor de documento vazio impede liberação mesmo com iframes prontos', async () => {
    const a = preparar({ semDocumentos: true });
    try {
        await a.iniciar(); a.instancias[0].callbacks.onReady();
        assert.equal(a.render().pronto, false);
        assert.equal(a.render().falhaCarregamento, true);
        assert.match(a.render().erro.message, /tipo de documento/);
    } finally { a.encerrar(); }
});

test('consulta de documentos vazia é falha recuperável', async () => {
    const a = preparar();
    try {
        await a.iniciar(); a.instancias[0].callbacks.onIdentificationTypesReceived(null, []);
        assert.equal(a.render().pronto, false);
        assert.match(a.render().erro.message, /tipos de documento/);
        assert.equal(a.timers.size, 0);
    } finally { a.encerrar(); }
});

test('timeout identifica falta de documentos em vez de culpar os iframes', async () => {
    const a = preparar({ semDocumentos: true });
    try {
        await a.iniciar(); [...a.timers.values()][0].callback();
        assert.match(a.render().erro.message, /tipos de documento/);
        assert.equal(a.instancias[0].desmontagens, 1);
    } finally { a.encerrar(); }
});
