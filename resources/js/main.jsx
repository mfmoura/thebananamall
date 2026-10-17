import './bootstrap';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import LojaApp from './LojaApp';

const container = document.getElementById('app');
if (container) createRoot(container).render(<StrictMode><LojaApp /></StrictMode>);
