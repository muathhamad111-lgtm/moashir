#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ENVIRONMENT="${1:-}"

require_root
assert_environment "${ENVIRONMENT}"

ENV_DIR="$(environment_dir "${ENVIRONMENT}")"
readonly ENV_DIR

main() {
    local active previous
    active="$(basename "$(readlink -f "${ENV_DIR}/current")")"
    previous="$(find "${ENV_DIR}/releases" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r | awk -v active="${active}" 'found { print; exit } $0 == active { found = 1 }')"

    [[ -n ${previous} ]] || die "No release older than ${active} is kept; nothing to roll back to."

    ln -sfn "${ENV_DIR}/releases/${previous}" "${ENV_DIR}/current.next"
    mv -Tf "${ENV_DIR}/current.next" "${ENV_DIR}/current"
    env_set_key "$(environment_env_file "${ENVIRONMENT}")" APP_RELEASE "${previous}"

    artisan "${ENVIRONMENT}" config:cache
    artisan "${ENVIRONMENT}" queue:restart
    artisan "${ENVIRONMENT}" horizon:terminate || supervisorctl start "moashir-${ENVIRONMENT}-horizon"

    info "Rolled back ${ENVIRONMENT}: ${active} -> ${previous}. Database migrations are forward-only and were NOT reverted."
}

main
