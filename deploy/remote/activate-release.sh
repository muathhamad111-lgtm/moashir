#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ENVIRONMENT="${1:-}"
readonly RELEASE="${2:-}"

require_root
assert_environment "${ENVIRONMENT}"
[[ ${RELEASE} =~ ^[0-9]{14}$ ]] || die "The release id must be a UTC timestamp (YYYYMMDDHHMMSS)."

ENV_DIR="$(environment_dir "${ENVIRONMENT}")"
readonly ENV_DIR
readonly RELEASE_DIR="${ENV_DIR}/releases/${RELEASE}"
readonly BACKEND="${RELEASE_DIR}/backend"

release_artisan() {
    (cd "${BACKEND}" && as_app "${MOASHIR_PHP_BIN}" artisan "$@")
}

prepare_permissions() {
    chown -R "${MOASHIR_APP_USER}:${MOASHIR_APP_USER}" "${RELEASE_DIR}"
    find "${RELEASE_DIR}" -type d -exec chmod 0750 {} +
    find "${RELEASE_DIR}" -type f -exec chmod 0640 {} +
    chgrp -R "${MOASHIR_WEB_GROUP}" "${RELEASE_DIR}/frontend"
    chmod 0751 "${RELEASE_DIR}" "${BACKEND}" "${BACKEND}/public"
    chmod 0750 "${BACKEND}/artisan"
}

link_shared() {
    rm -rf "${BACKEND}/storage"
    ln -sfn "${ENV_DIR}/shared/storage" "${BACKEND}/storage"
    ln -sfn "${ENV_DIR}/shared/.env" "${BACKEND}/.env"
    chown -h "${MOASHIR_APP_USER}:${MOASHIR_APP_USER}" "${BACKEND}/storage" "${BACKEND}/.env"
}

install_dependencies() {
    (cd "${BACKEND}" && as_app env COMPOSER_PROCESS_TIMEOUT=1200 "${MOASHIR_PHP_BIN}" "$(composer_bin)" install \
        --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist)
}

run_migrations() {
    local secrets
    secrets="$(environment_owner_secrets "${ENVIRONMENT}")"
    [[ -s ${secrets} ]] || die "Missing ${secrets}; run provision.sh system first."

    (
        set -a
        source "${secrets}"
        set +a
        cd "${BACKEND}"
        sudo -u "${MOASHIR_APP_USER}" -H --preserve-env=DB_USERNAME,DB_PASSWORD -- \
            "${MOASHIR_PHP_BIN}" artisan migrate --force --no-interaction
    )
}

apply_database_grants() {
    psql_admin -d "$(environment_database "${ENVIRONMENT}")" \
        -v database="$(environment_database "${ENVIRONMENT}")" \
        -v owner_role="$(environment_role "${ENVIRONMENT}" owner)" \
        -v app_role="$(environment_role "${ENVIRONMENT}" app)" \
        -v maintenance_role="$(environment_role "${ENVIRONMENT}" maintenance)" \
        -v skip_password_prompts=1 \
        <"${MOASHIR_KIT}/sql/production-roles.sql"
}

install_reference_data() {
    release_artisan moashir:permissions:sync --no-interaction
    release_artisan moashir:criteria:install-seed --no-interaction
    release_artisan moashir:regions:install-seed --no-interaction
    release_artisan moashir:breached-passwords:import database/data/breached-passwords/curated-seed.sha256 --format=sha256 --no-interaction
}

build_caches() {
    release_artisan config:cache
    release_artisan route:cache
    release_artisan view:cache
    release_artisan event:cache
}

switch_current() {
    ln -sfn "${RELEASE_DIR}" "${ENV_DIR}/current.next"
    mv -Tf "${ENV_DIR}/current.next" "${ENV_DIR}/current"
    info "current -> releases/${RELEASE}"
}

restart_workers() {
    local program="moashir-${ENVIRONMENT}-horizon"
    artisan "${ENVIRONMENT}" queue:restart

    if supervisorctl status "${program}" 2>/dev/null | grep -q RUNNING; then
        artisan "${ENVIRONMENT}" horizon:terminate
    else
        supervisorctl start "${program}" >/dev/null
    fi
    info "Horizon restarted on the new release."
}

remove_old_releases() {
    local old
    while IFS= read -r old; do
        [[ -n ${old} ]] && rm -rf "${ENV_DIR}/releases/${old}"
    done < <(find "${ENV_DIR}/releases" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | sort -r | tail -n "+$((MOASHIR_KEEP_RELEASES + 1))")
}

main() {
    [[ -d "${BACKEND}" && -f "${RELEASE_DIR}/frontend/index.html" ]] || die "Release ${RELEASE} is incomplete."

    prepare_permissions
    link_shared
    env_set_key "$(environment_env_file "${ENVIRONMENT}")" APP_RELEASE "${RELEASE}"
    env_set_key "$(environment_env_file "${ENVIRONMENT}")" SENTRY_RELEASE "${RELEASE}"
    install_dependencies
    run_migrations
    apply_database_grants
    install_reference_data
    build_caches
    switch_current
    restart_workers
    remove_old_releases
    info "Release ${RELEASE} active on ${ENVIRONMENT}."
}

main
