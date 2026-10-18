let carregamento;

export function carregarMercadoPago() {
    if (window.MercadoPago) return Promise.resolve(window.MercadoPago);
    if (!carregamento) carregamento = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://sdk.mercadopago.com/js/v2';
        script.async = true;
        const falhar = () => {
            clearTimeout(timer); script.remove(); carregamento = null;
            reject(new Error('Não foi possível carregar os campos seguros. Confira a conexão e o bloqueador de conteúdo e tente novamente.'));
        };
        const timer = window.setTimeout(falhar, 15000);
        script.onerror = falhar;
        script.onload = () => {
            clearTimeout(timer);
            if (window.MercadoPago) resolve(window.MercadoPago);
            else falhar();
        };
        document.head.append(script);
    });
    return carregamento;
}
