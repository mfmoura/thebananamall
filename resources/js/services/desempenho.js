// Telemetria temporária de desenvolvimento: somente métricas numéricas.
const hot = import.meta.hot;
let commits = 0;
let total = 0;
let maior = 0;
let timer;
const observadores = [];
const enviar = (tipo, dados) => hot?.send('lcb:desempenho', { tipo, ...dados });

export function medirRenderizacao(_id, _fase, duracao) {
    if (!hot) return;
    commits++;
    total += duracao;
    maior = Math.max(maior, duracao);
    if (!timer) timer = setTimeout(() => {
        enviar('react', { commits, total_ms: total, maior_ms: maior });
        commits = 0; total = 0; maior = 0; timer = undefined;
    }, 2000);
}

if (hot && typeof PerformanceObserver !== 'undefined') {
    const observar = (tipo, callback) => {
        if (!PerformanceObserver.supportedEntryTypes.includes(tipo)) return;
        const observer = new PerformanceObserver((lista) => lista.getEntries().forEach(callback));
        observer.observe({ type: tipo, buffered: true });
        observadores.push(observer);
    };
    observar('longtask', (entrada) => enviar('longtask', { duracao_ms: entrada.duration }));
    observar('largest-contentful-paint', (entrada) => enviar('lcp', { duracao_ms: entrada.startTime }));
    observar('layout-shift', (entrada) => {
        if (!entrada.hadRecentInput) enviar('layout-shift', { valor: entrada.value });
    });
    observar('resource', (entrada) => {
        // A rota é um rótulo fixo: nunca envia URL, parâmetros ou conteúdo.
        if (new URL(entrada.name).pathname === '/bff/api/clientes') {
            enviar('cadastro-http', { duracao_ms: entrada.duration });
        }
    });
    hot.dispose(() => {
        clearTimeout(timer);
        observadores.forEach((observer) => observer.disconnect());
    });
}
