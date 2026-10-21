#!/usr/bin/env bash
set -euo pipefail
fixture=$(mktemp -d)
trap 'rm -rf "$fixture"' EXIT
cat > "$fixture/curl" <<'MOCK'
#!/usr/bin/env bash
case "${*: -1}" in
    */dashboard/login) printf '<script src="/build/assets/main-abc.js"></script>' ;;
    */dashboard/session) printf '%s' "${SESSION_STATUS:-401}" ;;
    */main-abc.js) printf '%s' "${ASSET_STATUS:-200}"; exit "${ASSET_FAILURE:-0}" ;;
    *) exit 99 ;;
esac
MOCK
chmod +x "$fixture/curl"
export PATH="$fixture:$PATH"
revision=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
bash scripts/deploy/health.sh https://loja.example "$revision"
if SESSION_STATUS=200 bash scripts/deploy/health.sh https://loja.example "$revision"; then
    echo 'Uma sessão anônima autorizada precisa reprovar o deploy.' >&2; exit 1
fi
if ASSET_FAILURE=22 bash scripts/deploy/health.sh https://loja.example "$revision"; then
    echo 'Asset indisponível precisa reprovar o deploy.' >&2; exit 1
fi
if ASSET_STATUS=301 bash scripts/deploy/health.sh https://loja.example "$revision"; then exit 1; fi
if bash scripts/deploy/health.sh 'http://origem-invalida' "$revision"; then exit 1; fi
echo 'Verificações de publicação aprovadas.'
