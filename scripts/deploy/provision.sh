#!/usr/bin/env bash
set -Eeuo pipefail
umask 0027
base=$(realpath "${1:?Informe o diretório da aplicação}")
origin=${2:?Informe a origem HTTPS}
[[ "$base" != / && "$origin" =~ ^https://[a-zA-Z0-9.-]+(:[0-9]+)?$ ]] || exit 2
cd "$base"
test -f .env && test -d vendor && test -f docker-compose.override.yaml
if test -e .deploy/current || test -f docker-compose.deploy.yaml; then
    echo 'Layout já provisionado; nenhuma alteração realizada.' >&2
    exit 1
fi
if grep -q '^COMPOSE_FILE=' .env; then
    echo 'COMPOSE_FILE já configurado; ajuste manual necessário.' >&2
    exit 1
fi
mkdir -p .deploy/releases .deploy/backups .deploy/incoming
chmod 700 .deploy
legacy="$base/.deploy/releases/legacy-$(date -u +%Y%m%d%H%M%S)"
mkdir -p "$legacy"
tar --exclude='bootstrap/cache/*.php' --exclude=public/storage --exclude=public/hot \
    -cf - app bootstrap config database lang public resources routes vendor artisan composer.json composer.lock \
    | tar -xf - -C "$legacy"
mkdir -p "$legacy/storage"
touch "$legacy/.env"
ln -s /var/www/html/storage/app/public "$legacy/public/storage"
chmod -R a+rX "$legacy"
ln -s "$legacy" .deploy/current
printf 'DEPLOY_ORIGIN=%s\n' "$origin" > .deploy/server.conf
cp scripts/deploy/ssh-entrypoint.sh .deploy/ssh-entrypoint.sh
chmod 700 .deploy/ssh-entrypoint.sh
cat > docker-compose.deploy.yaml <<'COMPOSE'
# Gerado por scripts/deploy/provision.sh. Infraestrutura original preservada.
x-release-volumes: &release-volumes
  - ./.deploy/current:/var/www/html
  - ./.env:/var/www/html/.env:ro
  - ./storage:/var/www/html/storage
services:
  php:
    volumes: *release-volumes
  nginx:
    volumes: *release-volumes
  horizon:
    volumes: *release-volumes
  scheduler:
    volumes: *release-volumes
COMPOSE
# Mantém docker compose ps/logs/restart/up usando o layout publicado.
printf '\nCOMPOSE_FILE=docker-compose.yaml:docker-compose.override.yaml:docker-compose.deploy.yaml\n' >> .env
sudo docker compose config --quiet
echo 'Layout preparado. Os containers atuais continuam ativos até o primeiro deploy.'
