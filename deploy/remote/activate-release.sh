#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ENVIRONMENT="${1:-}"
readonly RELEASE="${2:-}"
readonly STALE_UPLOAD_MINUTES=1440

require_root
assert_environment "${ENVIRONMENT}"
assert_release_id "${RELEASE}"

ENV_DIR="$(environment_dir "${ENVIRONMENT}")"
readonly ENV_DIR
readonly RELEASE_DIR="${ENV_DIR}/releases/${RELEASE}"
readonly BACKEND="${RELEASE_DIR}/backend"
APP_USER="$(environment_runtime_user "${ENVIRONMENT}")"
readonly APP_USER

release_artisan() {
    (cd "${BACKEND}" && as_app "${ENVIRONMENT}" "${MOASHIR_PHP_BIN}" artisan "$@")
}

receive_release() {
    local incoming source owner
    incoming="$(environment_incoming "${ENVIRONMENT}")"
    source="${incoming}/${RELEASE}"

    [[ -d ${source} && ! -L ${source} ]] || die "Release ${RELEASE} was not uploaded to ${source}."
    owner="$(stat -c '%U' "${source}")"
    [[ ${owner} == "root" || ${owner} == "${MOASHIR_DEPLOY_USER}" ]] || die "${source} belongs to ${owner}; refused."
    [[ -z "$(find "${source}" -type l -print -quit)" ]] || die "Release ${RELEASE} contains symbolic links; refused."
    [[ -z "$(find "${source}" ! -type f ! -type d -print -quit)" ]] || die "Release ${RELEASE} contains special files; refused."
    [[ ! -e ${RELEASE_DIR} ]] || die "Release ${RELEASE} already exists in ${ENV_DIR}/releases."

    mv "${source}" "${RELEASE_DIR}"
    find "${incoming}" -mindepth 1 -maxdepth 1 -mmin "+${STALE_UPLOAD_MINUTES}" -exec rm -rf {} +
}

prepare_permissions() {
    chown -R "${APP_USER}:${APP_USER}" "${RELEASE_DIR}"
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
    chown -h "${APP_USER}:${APP_USER}" "${BACKEND}/storage" "${BACKEND}/.env"
}

label_release() {
    local env_file label
    env_file="$(environment_env_file "${ENVIRONMENT}")"
    label="$(release_label "${RELEASE_DIR}")"
    env_set_key "${env_file}" APP_RELEASE "${label}"
    env_set_key "${env_file}" SENTRY_RELEASE "${label}"
    info "Release label ${label} (commit $(release_revision "${RELEASE_DIR}" | grep . || printf 'not recorded'))."
}

install_dependencies() {
    (cd "${BACKEND}" && as_app "${ENVIRONMENT}" env COMPOSER_PROCESS_TIMEOUT=1200 "${MOASHIR_PHP_BIN}" "$(composer_bin)" install \
        --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist)
}

run_migrations() {
    local secrets
    secrets="$(environment_owner_secrets "${ENVIRONMENT}")"
    [[ -s ${secrets} ]] || die "Missing ${secrets}; run provision.sh system first."

    (
        DB_USERNAME="$(env_get_key "${secrets}" DB_USERNAME)"
        DB_PASSWORD="$(env_get_key "${secrets}" DB_PASSWORD)"
        export DB_USERNAME DB_PASSWORD
        cd "${BACKEND}"
        sudo -u "${APP_USER}" -H --preserve-env=DB_USERNAME,DB_PASSWORD -- \
            "${MOASHIR_PHP_BIN}" artisan migrate --force --no-interaction
    )
}

apply_database_grants() {
    environment_psql_admin "${ENVIRONMENT}" -d "$(environment_database "${ENVIRONMENT}")" \
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
    release_artisan moashir:breached-passwords:import database/data/breached-passwords --no-interaction
    release_artisan moashir:legal:install-drafts --no-interaction
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
    assert_posture "${ENVIRONMENT}"
    receive_release
    [[ -d "${BACKEND}" && -f "${RELEASE_DIR}/frontend/index.html" ]] || die "Release ${RELEASE} is incomplete."

    prepare_permissions
    link_shared
    label_release
    install_dependencies
    run_migrations
    apply_database_grants
    install_reference_data
    build_caches
    switch_current
    restart_workers
    remove_old_releases
    info "Release ${RELEASE} active on ${ENVIRONMENT} as ${APP_USER}."
}

main
