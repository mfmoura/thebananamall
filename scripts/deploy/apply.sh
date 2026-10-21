#!/usr/bin/env bash
set -Eeuo pipefail
umask 0027

base=$(realpath "${1:?Informe o diretório da aplicação}")
revision=${2:?Informe o commit}
[[ "$revision" =~ ^[a-f0-9]{40}$ && "$base" != / ]] || exit 2
cd "$base"
test -f .env && test -f docker-compose.yaml && test -f docker-compose.override.yaml
test -f .deploy/server.conf
# Arquivo provisionado no servidor, fora do Git e dos artefatos.
source .deploy/server.conf
: "${DEPLOY_ORIGIN:?Configure DEPLOY_ORIGIN}"
exec 9>.deploy/lock
flock -n 9 || { echo 'Outra publicação está em andamento.' >&2; exit 1; }
incoming="$base/.deploy/incoming/$revision"
release="$base/.deploy/releases/$revision-$(date -u +%Y%m%d%H%M%S)-$$"
test ! -e "$release"
cd "$incoming"
sha256sum -c release.tar.gz.sha256
# O artefato é produzido pelo workflow de branches autorizadas.
if tar -tzf release.tar.gz | sed 's#^\./##' | grep -E '(^/|(^|/)\.\.(/|$)|^\.env($|/)|^storage/|^\.docker/)'; then
    echo 'Pacote contém caminhos reservados.' >&2
    exit 1
fi
mkdir -p "$release"
tar -xzf release.tar.gz -C "$release" --no-same-owner
test "$(cat "$release/REVISION")" = "$revision"
test -f "$release/public/build/manifest.json"
test -f "$release/vendor/autoload.php"
mkdir -p "$release/storage" "$release/bootstrap/cache"
touch "$release/.env"
ln -s /var/www/html/storage/app/public "$release/public/storage"
chmod -R a+rX "$release"
cd "$base"
compose=(sudo docker compose --project-directory "$base" -f "$base/docker-compose.yaml" -f "$base/docker-compose.override.yaml")
test -f docker-compose.deploy.yaml && compose+=(-f "$base/docker-compose.deploy.yaml")
test -L .deploy/current || { echo 'Execute o provisionamento inicial antes de publicar.' >&2; exit 1; }
previous=$(readlink -f .deploy/current)
[[ "$previous" == "$base/.deploy/releases/"* ]]
[[ "$previous" != "$release" ]]

# O backup fica fora do diretório público. Nunca restaura o banco automaticamente.
mkdir -p .deploy/backups
backup=".deploy/backups/$(basename "$release").dump"
"${compose[@]}" exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$backup"
test -s "$backup"
maintenance=false
activated=false
migrating=false
recover() {
    code=$?
    trap - ERR
    set +e
    if $migrating; then
        echo 'Migração falhou. Manutenção e filas paradas preservadas; intervenção necessária. Banco não revertido.' >&2
    else
        rollback_failed=false
        if $activated; then
            "${compose[@]}" exec -T php php artisan down --retry=30 || rollback_failed=true
            "${compose[@]}" stop horizon scheduler || rollback_failed=true
            ln -s "$previous" .deploy/current.next
            mv -Tf .deploy/current.next .deploy/current
            "${compose[@]}" up -d --no-deps --force-recreate php nginx || rollback_failed=true
            "${compose[@]}" exec -T php php artisan optimize:clear || rollback_failed=true
        fi
        if $rollback_failed; then
            echo 'Recuperação incompleta. Verifique manutenção e filas antes de liberar o servidor.' >&2
        else
            if $maintenance; then
                "${compose[@]}" exec -T php php artisan up
                "${compose[@]}" up -d --no-deps --force-recreate horizon scheduler
            fi
            echo 'Publicação falhou; versão anterior restaurada. Migrações já aplicadas permanecem.' >&2
        fi
    fi
    exit "$code"
}
trap recover ERR
"${compose[@]}" exec -T php php artisan down --retry=30
maintenance=true
"${compose[@]}" stop horizon scheduler
ln -s "$release" .deploy/current.next
mv -Tf .deploy/current.next .deploy/current
activated=true
"${compose[@]}" up -d --no-deps --force-recreate php nginx
"${compose[@]}" exec -T php php artisan optimize:clear
"${compose[@]}" exec -T php php artisan package:discover --ansi
migrating=true
"${compose[@]}" exec -T php php artisan migrate --force
migrating=false
"${compose[@]}" exec -T php php artisan up
bash "$release/scripts/deploy/health.sh" "$DEPLOY_ORIGIN" "$revision"
"${compose[@]}" up -d --no-deps --force-recreate horizon scheduler
for attempt in {1..10}; do
    if "${compose[@]}" exec -T php php artisan horizon:status; then break; fi
    test "$attempt" -lt 10
    sleep 2
done
printf '%s\n' "$revision" > .deploy/deployed-version
printf 'Publicação concluída: %s\n' "$revision"
