#!/usr/bin/env bash
set -euo pipefail

# A origem é definida no servidor, nunca pelo pacote publicado.
origin=${1:?Informe a origem HTTPS}
revision=${2:?Informe o commit}
[[ "$origin" =~ ^https://[a-zA-Z0-9.-]+(:[0-9]+)?$ ]] || exit 2
[[ "$revision" =~ ^[a-f0-9]{40}$ ]] || exit 2
host=${origin#https://}
html=$(curl --fail --silent --show-error --max-time 20 \
    -H "Host: $host" -H 'X-Forwarded-Proto: https' http://127.0.0.1/dashboard/login)
asset=$(printf '%s' "$html" | grep -oE '/build/assets/main-[a-zA-Z0-9_-]+\.js' | head -1)
[[ -n "$asset" ]]
asset_status=$(curl --fail --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' \
    -H "Host: $host" -H 'X-Forwarded-Proto: https' "http://127.0.0.1$asset")
[[ "$asset_status" == 200 ]]
# Sessão anônima deve continuar recusada; o deploy não autentica usuários.
status=$(curl --silent --show-error --max-time 20 -o /dev/null -w '%{http_code}' \
    -H "Host: $host" -H 'X-Forwarded-Proto: https' -H 'Accept: application/json' \
    http://127.0.0.1/dashboard/session)
[[ "$status" == 401 ]]
printf 'Versão %s: login, asset e proteção de sessão verificados.\n' "$revision"
