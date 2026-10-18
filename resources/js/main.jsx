import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { Profiler, StrictMode } from 'react';
import { medirRenderizacao } from './services/desempenho';
import { createRoot } from 'react-dom/client';
import LojaApp from './LojaApp';
import DashboardApp from './DashboardApp';
import { ProcessamentoProvider } from './context/ProcessamentoContext';

const Aplicacao = window.location.pathname === '/dashboard' || window.location.pathname.startsWith('/dashboard/') ? DashboardApp : LojaApp;

const container = document.getElementById('app');
if (container) createRoot(container).render(<StrictMode><ProcessamentoProvider>{import.meta.env.DEV
    ? <Profiler id="loja" onRender={medirRenderizacao}><Aplicacao /></Profiler>
    : <Aplicacao />}</ProcessamentoProvider></StrictMode>);
