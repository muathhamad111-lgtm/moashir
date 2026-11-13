#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

refuse() {
    printf 'REFUSED: %s\n' "$*" >&2
    exit 1
}

expect_words() {
    [[ $1 -eq $2 ]] || refuse "wrong number of arguments"
}

receive_upload() {
    local environment="$1" release="$2" destination
    destination="$(environment_incoming "${environment}")/${release}"
    [[ ! -e ${destination} ]] || refuse "release ${release} was already uploaded"
    mkdir -m 0700 "${destination}"
    tar -C "${destination}" --no-same-owner --no-same-permissions -xzf -
    printf 'Release %s received for %s.\n' "${release}" "${environment}"
}

privileged() {
    exec sudo -n /usr/bin/bash "${MOASHIR_KIT}/remote/release-entry.sh" "$@"
}

main() {
    local -a words=()
    read -r -a words <<<"${SSH_ORIGINAL_COMMAND:-}"
    [[ ${#words[@]} -ge 1 ]] || refuse "no command"

    case "${words[0]}" in
        kit-digest)
            expect_words "${#words[@]}" 1
            cat "${MOASHIR_KIT}/KIT_DIGEST"
            ;;
        upload)
            expect_words "${#words[@]}" 3
            assert_environment "${words[1]}"
            assert_release_id "${words[2]}"
            receive_upload "${words[1]}" "${words[2]}"
            ;;
        activate)
            expect_words "${#words[@]}" 3
            assert_environment "${words[1]}"
            assert_release_id "${words[2]}"
            privileged activate "${words[1]}" "${words[2]}"
            ;;
        smoke | rollback)
            expect_words "${#words[@]}" 2
            assert_environment "${words[1]}"
            privileged "${words[0]}" "${words[1]}"
            ;;
        *) refuse "unknown command" ;;
    esac
}

main
