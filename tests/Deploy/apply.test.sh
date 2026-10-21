#!/usr/bin/env bash
set -euo pipefail
fixture=$(mktemp -d)
trap 'rm -rf "$fixture"' EXIT
apply="$PWD/scripts/deploy/apply.sh"
revision=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
mkdir -p "$fixture/bin"
cat > "$fixture/bin/sudo" <<'MOCK'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$FAKE_COMPOSE_LOG"
case "$*" in
    *'exec -T db '*) printf 'backup PostgreSQL simulado' ;;
    *'artisan migrate --force'*) exit "${MIGRATION_FAILURE:-0}" ;;
esac
MOCK
chmod +x "$fixture/bin/sudo"
export PATH="$fixture/bin:$PATH"

prepare() {
    base="$fixture/$1"
    mkdir -p "$base/.deploy/releases/old" "$base/.deploy/incoming/$revision" "$base/package/vendor" \
        "$base/package/public/build" "$base/package/scripts/deploy"
    touch "$base/.env" "$base/docker-compose.yaml" "$base/docker-compose.override.yaml" \
        "$base/docker-compose.deploy.yaml" "$base/package/vendor/autoload.php" "$base/package/public/build/manifest.json"
    printf 'DEPLOY_ORIGIN=https://loja.example\n' > "$base/.deploy/server.conf"
    printf '%s\n' "$revision" > "$base/package/REVISION"
    printf '#!/bin/bash\nexit %s\n' "$2" > "$base/package/scripts/deploy/health.sh"
    ln -s "$base/.deploy/releases/old" "$base/.deploy/current"
    tar -czf "$base/.deploy/incoming/$revision/release.tar.gz" -C "$base/package" .
    (cd "$base/.deploy/incoming/$revision"; sha256sum release.tar.gz > release.tar.gz.sha256)
    export FAKE_COMPOSE_LOG="$base/compose.log"
}

prepare success 0
bash "$apply" "$base" "$revision"
test "$(cat "$base/.deploy/deployed-version")" = "$revision"
test "$(readlink -f "$base/.deploy/current")" != "$base/.deploy/releases/old"
test -s "$(find "$base/.deploy/backups" -type f -name '*.dump')"

prepare health-failure 1
if bash "$apply" "$base" "$revision"; then exit 1; fi
test "$(readlink -f "$base/.deploy/current")" = "$base/.deploy/releases/old"
test ! -f "$base/.deploy/deployed-version"
grep -q 'artisan up' "$FAKE_COMPOSE_LOG"

prepare migration-failure 0
if MIGRATION_FAILURE=1 bash "$apply" "$base" "$revision"; then exit 1; fi
test "$(readlink -f "$base/.deploy/current")" != "$base/.deploy/releases/old"
if grep -q 'artisan up' "$FAKE_COMPOSE_LOG"; then exit 1; fi
if grep -q 'force-recreate horizon scheduler' "$FAKE_COMPOSE_LOG"; then exit 1; fi
test ! -f "$base/.deploy/deployed-version"
echo 'Ativação, recuperação de código e falha de migration validadas.'
