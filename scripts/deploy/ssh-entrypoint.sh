#!/usr/bin/env bash
set -euo pipefail
umask 0077
base=$(realpath "${1:?Informe o diretório da aplicação}")
cd "$base"
read -r operation revision checksum extra <<< "${SSH_ORIGINAL_COMMAND:-}"
case "$operation" in
    status)
        [[ -z "${revision:-}${checksum:-}${extra:-}" ]]
        cat .deploy/deployed-version 2>/dev/null || echo legacy
        ;;
    upload)
        [[ "$revision" =~ ^[a-f0-9]{40}$ && "$checksum" =~ ^[a-f0-9]{64}$ && -z "${extra:-}" ]]
        incoming="$base/.deploy/incoming/$revision"
        mkdir -p "$incoming"
        # Limite de 250 MiB; pacotes truncados não passam pelo checksum.
        temporary=$(mktemp "$incoming/upload.XXXXXX")
        trap 'rm -f "$temporary"' EXIT
        head -c 262144000 > "$temporary"
        printf '%s  %s\n' "$checksum" "$temporary" | sha256sum -c -
        mv "$temporary" "$incoming/release.tar.gz"
        printf '%s  release.tar.gz\n' "$checksum" > "$incoming/release.tar.gz.sha256"
        ;;
    deploy)
        [[ "$revision" =~ ^[a-f0-9]{40}$ && -z "${checksum:-}${extra:-}" ]]
        cd "$base/.deploy/incoming/$revision"
        sha256sum -c release.tar.gz.sha256
        tar -xzf release.tar.gz scripts/deploy
        exec bash scripts/deploy/apply.sh "$base" "$revision"
        ;;
    *) echo 'Comando SSH não autorizado.' >&2; exit 2 ;;
esac
