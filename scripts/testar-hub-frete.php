<?php

// Diagnóstico manual: token no .env ou stdin, sem bootstrap Laravel/Telescope nem logs de URLs.
// Não compra etiquetas e não cria pedidos. O script para no primeiro erro para evitar consultas inúteis.
require __DIR__.'/../vendor/autoload.php';

$environment = \Dotenv\Dotenv::createArrayBacked(dirname(__DIR__))->safeLoad();
$token = trim((string) (($environment['HUB_DESENVOLVEDOR_TOKEN'] ?? '') ?: getenv('HUB_DESENVOLVEDOR_TOKEN')));
if ($token === '') {
    $token = trim((string) fgets(STDIN));
}
if ($token === '') {
    fwrite(STDERR, "Configure HUB_DESENVOLVEDOR_TOKEN no .env ou informe pelo stdin.\n");
    exit(1);
}

$destinos = \Database\Seeders\FreteTesteSeeder::DESTINOS;
$opcoes = getopt('', ['primeira', 'peso:', 'tipo-servico:', 'formato:']);
$servicos = ['PAC' => '04510', 'SEDEX' => '04014'];
if (in_array('--primeira', $argv, true)) {
    $destinos = array_slice($destinos, 0, 1);
    $servicos = array_slice($servicos, 0, 1);
}
if (isset($opcoes['tipo-servico'])) {
    $servicos = ['Diagnóstico' => (string) $opcoes['tipo-servico']];
}
foreach ($destinos as $cidade => $cep) {
    foreach ($servicos as $nome => $codigo) {
        $parametros = [
            'servico' => 'calculoFrete', 'cepOrigem' => '03044000', 'cepDestino' => $cep,
            // Neste endpoint, 0.5 foi aceito como 500 g; 500 retornou excesso de peso.
            'altura' => 10, 'largura' => 15, 'comprimento' => 20, 'peso' => $opcoes['peso'] ?? '0.5',
            'formato' => $opcoes['formato'] ?? 1, 'tipoServico' => $codigo, 'token' => $token,
        ];
        $curl = curl_init('https://ws.hubdodesenvolvedor.com.br/v2/correios/?'.http_build_query($parametros));
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => false]);
        $inicio = microtime(true);
        $body = curl_exec($curl);
        $http = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $erro = curl_errno($curl);
        curl_close($curl);
        $resposta = is_string($body) ? str_replace($token, '[REMOVIDO]', $body) : '';
        $json = json_decode($resposta, true);
        echo json_encode([
            'destino' => $cidade, 'cep' => $cep, 'servico' => $nome, 'codigo' => $codigo,
            'origem' => '03044000', 'peso_enviado' => $parametros['peso'], 'formato' => $parametros['formato'],
            'http' => $http, 'duracao_ms' => (int) round((microtime(true) - $inicio) * 1000),
            'erro_transporte' => $erro, 'resposta' => $json ?? mb_substr($resposta, 0, 3000),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
        if ($erro || $http !== 200 || ! is_array($json) || in_array($json['status'] ?? null, [false, 0, 'false', 'erro', 'error'], true)) {
            exit(2);
        }
        usleep(1700000);
    }
}
