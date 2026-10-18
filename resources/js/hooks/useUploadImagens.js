import { useEffect, useRef, useState } from 'react';

const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_BYTES = 5 * 1024 * 1024;
const MAX_ARQUIVOS = 10;

export default function useUploadImagens({ desabilitado = false } = {}) {
    const [arquivos, setArquivos] = useState([]);
    const [erro, setErro] = useState('');
    const [arrastando, setArrastando] = useState(false);
    const inputRef = useRef(null);
    const selecao = useRef([]);
    const urls = useRef(new Map());
    useEffect(() => () => { urls.current.forEach((url) => URL.revokeObjectURL(url)); urls.current.clear(); }, []);

    const adicionar = (recebidos) => {
        if (desabilitado) return;
        const falhas = [];
        const novos = [...selecao.current];
        for (const arquivo of Array.from(recebidos)) {
            if (!TIPOS.includes(arquivo.type)) { falhas.push(`${arquivo.name}: use JPEG, PNG ou WebP.`); continue; }
            if (arquivo.size > MAX_BYTES) { falhas.push(`${arquivo.name}: o limite é 5 MB.`); continue; }
            if (novos.some((item) => item.arquivo.name === arquivo.name && item.arquivo.size === arquivo.size && item.arquivo.lastModified === arquivo.lastModified)) continue;
            if (novos.length >= MAX_ARQUIVOS) { falhas.push('Selecione até 10 imagens por vez.'); break; }
            const id = crypto.randomUUID();
            const previa = URL.createObjectURL(arquivo);
            urls.current.set(id, previa);
            novos.push({ id, arquivo, previa });
        }
        selecao.current = novos;
        setArquivos(novos);
        setErro(falhas.join(' '));
    };

    const remover = (id) => {
        const url = urls.current.get(id);
        if (url) URL.revokeObjectURL(url);
        urls.current.delete(id);
        selecao.current = selecao.current.filter((item) => item.id !== id);
        setArquivos(selecao.current);
    };

    return {
        arquivos, erro, arrastando, remover, inputRef,
        inputProps: {
            type: 'file', accept: TIPOS.join(','), multiple: true, disabled: desabilitado,
            onChange: (event) => { adicionar(event.target.files); event.target.value = ''; },
        },
        zonaProps: {
            tabIndex: desabilitado ? -1 : 0,
            onDragOver: (event) => { event.preventDefault(); if (!desabilitado) { event.dataTransfer.dropEffect = 'copy'; setArrastando(true); } },
            onDragLeave: (event) => { if (!event.currentTarget.contains(event.relatedTarget)) setArrastando(false); },
            onDrop: (event) => { event.preventDefault(); setArrastando(false); adicionar(event.dataTransfer.files); },
            onPaste: (event) => {
                const imagens = Array.from(event.clipboardData.items).filter((item) => item.kind === 'file').map((item) => item.getAsFile()).filter(Boolean);
                if (imagens.length) { event.preventDefault(); adicionar(imagens); }
            },
            onKeyDown: (event) => {
                if (event.target === event.currentTarget && ['Enter', ' '].includes(event.key) && !desabilitado) { event.preventDefault(); inputRef.current?.click(); }
            },
        },
    };
}
