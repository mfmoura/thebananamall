<?php

namespace App\Console\Commands;

use Database\Seeders\BffClientSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallBff extends Command
{
    protected $signature = 'bff:install';
    protected $description = 'Configura a credencial confidencial do BFF sem exibi-la';

    public function handle(): int
    {
        $secret = config('bff.client_secret');
        if (! is_string($secret) || strlen($secret) < 32) {
            $path = app()->environmentFilePath();
            if (! is_file($path) || ! is_writable($path)) {
                $this->error('Configure BFF_CLIENT_SECRET no ambiente ou permita a gravação do .env.');
                return self::FAILURE;
            }
            $secret = Str::random(64);
            $conteudo = file_get_contents($path);
            $linha = 'BFF_CLIENT_SECRET='.$secret;
            $conteudo = preg_match('/^BFF_CLIENT_SECRET=.*$/m', $conteudo)
                ? preg_replace('/^BFF_CLIENT_SECRET=.*$/m', $linha, $conteudo)
                : rtrim($conteudo).PHP_EOL.$linha.PHP_EOL;
            file_put_contents($path, $conteudo, LOCK_EX);
        }
        config(['bff.client_secret' => $secret]);
        app(BffClientSeeder::class)->run();
        $this->callSilent('config:clear');
        $this->info('Cliente BFF configurado. Nenhuma credencial foi exibida.');
        return self::SUCCESS;
    }
}
