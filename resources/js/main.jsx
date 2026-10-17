import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { Profiler, StrictMode } from 'react';
import { medirRenderizacao } from './services/desempenho';
import { createRoot } from 'react-dom/client';
import LojaApp from './LojaApp';

const container = document.getElementById('app');
if (container) createRoot(container).render(<StrictMode>{import.meta.env.DEV
    ? <Profiler id="loja" onRender={medirRenderizacao}><LojaApp /></Profiler>
    : <LojaApp />}</StrictMode>);
