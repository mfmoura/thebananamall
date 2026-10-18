import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ command }) => ({
    base: command === 'serve' ? '/vite/' : undefined,
    plugins: [
        {
            name: 'lcb-desempenho-local',
            apply: 'serve',
            configureServer(server) {
                server.ws.on('lcb:desempenho', (dados) => {
                    const campos = {
                        react: ['commits', 'total_ms', 'maior_ms'],
                        longtask: ['duracao_ms'], lcp: ['duracao_ms'],
                        'layout-shift': ['valor'], 'cadastro-http': ['duracao_ms'],
                    };
                    const permitidos = campos[dados?.tipo];
                    if (!Array.isArray(permitidos)) return;
                    const metricas = Object.fromEntries(permitidos.map((chave) => [chave,
                        typeof dados[chave] === 'number' && Number.isFinite(dados[chave])
                            ? Math.round(Math.max(0, Math.min(1e7, dados[chave])) * 1000) / 1000 : 0,
                    ]));
                    server.config.logger.info(`[desempenho] ${dados.tipo} ${JSON.stringify(metricas)}`);
                });
            },
        },
        laravel({
            input: ['resources/css/app.css', 'resources/js/main.jsx'],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        allowedHosts: ['nginx'],
        origin: 'http://localhost',
        cors: {
            origin: ['http://localhost', 'http://127.0.0.1'],
        },
        hmr: {
            host: 'localhost',
            clientPort: 80,
        },
        watch: {
            usePolling: true,
            interval: 1000,
            ignored: ['**/storage/**', '**/vendor/**', '**/public/build/**'],
        },
    },
}));
