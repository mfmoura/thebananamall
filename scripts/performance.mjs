import { performance } from 'node:perf_hooks';
import { mkdtemp, readFile, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const temporario = await mkdtemp(join(tmpdir(), 'lcb-perf-'));
try {
    const arquivo = new URL('../composer.json', import.meta.url);
    const local = join(temporario, 'composer.json');
    await writeFile(local, await readFile(arquivo));
    for (const [nome, path] of [['Volume do projeto', arquivo], ['Disco temporário do container', local]]) {
        const inicio = performance.now();
        for (let i = 0; i < 100; i++) await readFile(path);
        console.log(JSON.stringify({ medicao: '100 leituras sequenciais do mesmo arquivo', nome, ms: Math.round(performance.now() - inicio) }));
    }
} finally {
    await rm(temporario, { recursive: true, force: true });
}

const loja = process.env.PERF_URL || 'http://nginx';
const vite = process.env.PERF_VITE_URL || 'http://localhost:5173';
const amostras = Math.max(1, Math.min(10, Number(process.env.PERF_SAMPLES) || 3));
const cookies = new Map();
const resultados = [];
for (const [nome, url] of [
    ['HTML Laravel', `${loja}/`],
    ['Sessão BFF', `${loja}/bff/session`],
    ['Catálogo BFF (5 produtos)', `${loja}/bff/api/produtos?per_page=5&page=1`],
    ['Entrada React Vite', `${vite}/resources/js/main.jsx`],
    ['CSS Vite', `${vite}/resources/css/app.css`],
]) {
    for (let amostra = 1; amostra <= amostras; amostra++) {
        const inicio = performance.now();
        try {
            const resposta = await fetch(url, {
                headers: { Accept: nome === 'HTML Laravel' ? 'text/html' : '*/*', Cookie: [...cookies].map(([k, v]) => `${k}=${v}`).join('; ') },
                signal: AbortSignal.timeout(60000),
            });
            const cabecalhos = performance.now();
            if (url.startsWith(loja)) {
                for (const cookie of resposta.headers.getSetCookie()) {
                    const [nomeCookie, ...valor] = cookie.split(';')[0].split('=');
                    cookies.set(nomeCookie, valor.join('='));
                }
            }
            const corpo = await resposta.arrayBuffer();
            const medida = { nome, amostra, status: resposta.status, ttfb_ms: Math.round(cabecalhos - inicio), total_ms: Math.round(performance.now() - inicio), bytes: corpo.byteLength, servidor: resposta.headers.get('server-timing') || '' };
            resultados.push(medida);
            console.log(JSON.stringify(medida));
        } catch (erro) {
            resultados.push({ nome, amostra, erro: erro.message });
        }
    }
}
console.table(resultados);
console.log('Medição HTTP pelo Node; não mede renderização, layout nem interação do navegador. Primeira amostra pode incluir aquecimento.');
