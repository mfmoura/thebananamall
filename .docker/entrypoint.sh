#!/bin/bash

umask 0002

# Laravel precisa gravar nessas pastas durante a execucao.
for directory in storage bootstrap/cache; do
    if [ -d "$directory" ]; then
        chmod -R ug+rwX "$directory"
    fi
done

if [ ! -d "vendor" ]; then
    echo "Diretorio 'vendor' nao encontrado. Executando 'composer install'..."
    composer install
fi

if [ ! -f ".env" ]; then
    echo "Arquivo '.env' nao encontrado. Copiando '.env.example' para '.env'..."
    cp .env.example .env
    echo "Gerando a chave da aplicacao..."
    php artisan key:generate
    php artisan migrate --force
fi

echo "Iniciando PHP-FPM..."
exec php-fpm
