#!/usr/bin/env bash
set -euo pipefail
fixture=$(mktemp -d)
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/.deploy"
entrypoint="$PWD/scripts/deploy/ssh-entrypoint.sh"
revision=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
printf 'pacote de teste' > "$fixture/package"
checksum=$(sha256sum "$fixture/package" | cut -d ' ' -f 1)
SSH_ORIGINAL_COMMAND="upload $revision $checksum" bash "$entrypoint" "$fixture" < "$fixture/package"
cmp "$fixture/package" "$fixture/.deploy/incoming/$revision/release.tar.gz"
test "$(SSH_ORIGINAL_COMMAND=status bash "$entrypoint" "$fixture")" = legacy
for command in 'bash' 'cat .env' 'upload ../../etc/passwd abc' 'status extra' 'deploy ../invalid'; do
    if SSH_ORIGINAL_COMMAND="$command" bash "$entrypoint" "$fixture"; then
        echo 'O comando inválido foi autorizado.' >&2; exit 1
    fi
done
if SSH_ORIGINAL_COMMAND="upload $revision $(printf '%064d' 0)" bash "$entrypoint" "$fixture" < "$fixture/package"; then
    echo 'Checksum incorreto foi autorizado.' >&2; exit 1
fi
cmp "$fixture/package" "$fixture/.deploy/incoming/$revision/release.tar.gz"
echo 'Comando forçado SSH validado.'
