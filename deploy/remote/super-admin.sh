#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ENVIRONMENT="${1:-}"

require_root
assert_environment "${ENVIRONMENT}"
[[ -t 0 ]] || die "Run this through an interactive session (deploy.sh <environment> super-admin)."

main() {
    local email name
    read -r -p "Super Admin e-mail address: " email
    read -r -p "Super Admin full name: " name
    [[ -n ${email} && -n ${name} ]] || die "Both the e-mail address and the name are required."

    artisan "${ENVIRONMENT}" config:clear
    trap 'artisan "${ENVIRONMENT}" config:cache' EXIT

    export SUPER_ADMIN_INITIAL_EMAIL="${email}"
    (cd "$(environment_backend "${ENVIRONMENT}")" && sudo -u "${MOASHIR_APP_USER}" -H --preserve-env=SUPER_ADMIN_INITIAL_EMAIL -- \
        "${MOASHIR_PHP_BIN}" artisan moashir:super-admin grant --name="${name}")
}

main
