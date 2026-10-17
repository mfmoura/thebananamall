const rotaLoja = /^\/(?:produtos(?:\/\d+)?|carrinho|checkout|conta|pedidos(?:\/\d+)?)?$/;

export function destinoSeguro(valor) {
    if (typeof valor !== 'string' || !valor.startsWith('/') || valor.startsWith('//')) return null;
    try {
        const url = new URL(valor, window.location.origin);
        return url.origin === window.location.origin && rotaLoja.test(url.pathname)
            ? `${url.pathname}${url.search}${url.hash}` : null;
    } catch { return null; }
}

export function retornoAposLogin() {
    const destino = destinoSeguro(sessionStorage.getItem('lcb.destino'))
        || destinoSeguro(sessionStorage.getItem('lcb.ultimaPagina')) || '/';
    sessionStorage.removeItem('lcb.destino');
    return destino;
}

export function retornoAposLogout(location) {
    return /^\/(?:conta|checkout|pedidos)(?:\/|$)/.test(location.pathname)
        ? '/' : destinoSeguro(`${location.pathname}${location.search}${location.hash}`) || '/';
}
