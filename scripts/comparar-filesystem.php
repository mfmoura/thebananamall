<?php

// Diagnóstico CLI sem requisições HTTP, banco ou alterações de configuração.
$base = realpath($argv[1] ?? dirname(__DIR__));
if ($base === false || ! is_file($base.'/bootstrap/app.php')) {
    throw new RuntimeException('Informe a raiz de uma cópia do projeto.');
}
$inicio = hrtime(true);
require $base.'/vendor/autoload.php';
$autoload = hrtime(true);
$app = require $base.'/bootstrap/app.php';
// Usa exatamente o mesmo ambiente, sem copiar credenciais para a área temporária.
$app->useEnvironmentPath('/var/www/html');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$boot = hrtime(true);
echo json_encode([
    'base' => $base,
    'autoload_ms' => round(($autoload - $inicio) / 1e6, 2),
    'bootstrap_ms' => round(($boot - $autoload) / 1e6, 2),
    'total_ms' => round(($boot - $inicio) / 1e6, 2),
    'memoria_pico_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
    'arquivos_carregados' => count(get_included_files()),
    'pcov' => ini_get('pcov.enabled'),
    'opcache_cli' => ini_get('opcache.enable_cli'),
], JSON_UNESCAPED_SLASHES).PHP_EOL;
