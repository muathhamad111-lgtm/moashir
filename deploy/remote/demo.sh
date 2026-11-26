#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ENVIRONMENT="${1:-}"
readonly ACTION="${2:-}"

require_root
assert_environment "${ENVIRONMENT}"

case "${ACTION}" in
    install) readonly COMMAND="moashir:demo:install" ;;
    rotate-passwords) readonly COMMAND="moashir:demo:rotate-passwords" ;;
    *) die "The action must be install or rotate-passwords." ;;
esac

environment_is_demo "${ENVIRONMENT}" \
    || die "Demo data is refused: ${ENVIRONMENT} is not in the demo posture (MOASHIR_DEMO=true). Opt in only for an environment that holds fictional data: provision.sh posture demo ${ENVIRONMENT}."

readonly DESTINATION="/root/moashir-${ENVIRONMENT}-demo-accounts.txt"

WORKDIR=""

remove_workdir() {
    [[ -n ${WORKDIR} && -d ${WORKDIR} ]] || return 0

    if [[ -f "${WORKDIR}/accounts.txt" ]]; then
        shred -u "${WORKDIR}/accounts.txt"
    fi

    rmdir "${WORKDIR}"
}

main() {
    WORKDIR="$(mktemp -d "$(environment_dir "${ENVIRONMENT}")/shared/demo.XXXXXX")"
    trap remove_workdir EXIT
    chown "$(environment_runtime_user "${ENVIRONMENT}"):$(environment_runtime_user "${ENVIRONMENT}")" "${WORKDIR}"
    chmod 0700 "${WORKDIR}"

    artisan "${ENVIRONMENT}" "${COMMAND}" --accounts-file="${WORKDIR}/accounts.txt" --no-interaction
    install -m 600 -o root -g root "${WORKDIR}/accounts.txt" "${DESTINATION}"
    info "Demo accounts written to ${DESTINATION} (root only, mode 600). Read it on the server; never copy it into chat or the repository."
}

main
