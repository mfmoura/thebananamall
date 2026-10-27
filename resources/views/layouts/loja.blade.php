<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="marca-nome" content="{{ config('marca.nome') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('marca.nome'))</title>
    <link rel="icon" href="/favicon.ico" sizes="16x16 32x32 48x48 64x64 128x128 256x256">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/main.jsx'])
</head>
<body class="loja">
    <div id="app"></div>
    <noscript>Ative o JavaScript para acessar a loja.</noscript>
</body>
</html>
