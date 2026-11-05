#!/usr/bin/env bash

readonly LEGACY_PROCESS_WAIT_SECONDS=180

ensure_env_user() {
    local environment="$1" user home
    user="$(environment_user "${environment}")"
    home="$(environment_home "${environment}")"

    if ! id "${user}" >/dev/null 2>&1; then
        useradd --system --user-group --home-dir "${home}" --create-home --shell /usr/sbin/nologin "${user}"
        info "System user ${user} created."
    fi

    install -d -m 0750 -o "${user}" -g "${user}" "${home}"

    if getent group clamav >/dev/null && ! id -nG "${user}" | grep -qw clamav; then
        usermod -aG clamav "${user}"
        FPM_NEEDS_RELOAD=1
        info "${user} may now reach the ClamAV socket."
    fi
}

assert_environment_migrated() {
    environment_uses_own_user "$1" \
        || die "${1} still runs as the shared user ${MOASHIR_LEGACY_USER}. Run provision.sh migrate-user ${1} first (RUNBOOK: Applying Task-52 to an existing server)."
}

secure_base_directory() {
    install -d -m 0711 -o root -g root "${MOASHIR_BASE}"
}

legacy_processes_in() {
    local directory="$1" pid cwd
    while IFS= read -r pid; do
        [[ -n ${pid} ]] || continue
        cwd="$(readlink "/proc/${pid}/cwd" 2>/dev/null || true)"
        if [[ ${cwd} == "${directory}"* ]]; then
            printf '%s\n' "${pid}"
        fi
    done < <(pgrep -u "${MOASHIR_LEGACY_USER}" || true)
}

wait_for_legacy_processes() {
    local directory="$1" waited=0
    while [[ -n "$(legacy_processes_in "${directory}")" ]]; do
        [[ ${waited} -lt ${LEGACY_PROCESS_WAIT_SECONDS} ]] \
            || die "Processes of ${MOASHIR_LEGACY_USER} still work in ${directory} ($(legacy_processes_in "${directory}" | paste -sd ' ' -)). Wait for them and run migrate-user again."
        sleep 5
        waited=$((waited + 5))
    done
}

hand_over_tree() {
    local directory="$1" user="$2"
    [[ -d ${directory} ]] || return 0
    chown -R --from="${MOASHIR_LEGACY_USER}:${MOASHIR_LEGACY_USER}" "${user}:${user}" "${directory}"
    chown -R --from="${MOASHIR_LEGACY_USER}" "${user}" "${directory}"
    chown -R --from=":${MOASHIR_LEGACY_USER}" ":${user}" "${directory}"
}

stop_legacy_environment() {
    local environment="$1"
    info "Maintenance mode for ${environment} while its files change owner."
    artisan "${environment}" down --retry=60 || warn "artisan down failed; continuing."
    supervisorctl stop "moashir-${environment}-horizon" >/dev/null 2>&1 || true
    rm -f "/etc/cron.d/moashir-${environment}"
    wait_for_legacy_processes "$(environment_dir "${environment}")/"
}

verify_environment_user() {
    local environment="$1" user
    user="$(environment_user "${environment}")"
    [[ "$(stat -c '%U' "$(environment_env_file "${environment}")")" == "${user}" ]] || die ".env of ${environment} does not belong to ${user}."
    grep -qx "user = ${user}" "${MOASHIR_FPM_POOL_DIR}/moashir-${environment}.conf" || die "The PHP-FPM pool of ${environment} does not run as ${user}."
    grep -qx "user=${user}" "/etc/supervisor/conf.d/moashir-${environment}-horizon.conf" || die "Horizon of ${environment} does not run as ${user}."
    info "${environment} runs as ${user} (files, PHP-FPM pool, Horizon, scheduler)."
}

migrate_user() {
    local environment="$1" user
    assert_environment "${environment}"
    [[ -d "$(environment_dir "${environment}")" ]] || die "${environment} is not installed on this server."
    user="$(environment_user "${environment}")"
    FPM_NEEDS_RELOAD=1

    ensure_env_user "${environment}"
    if ! environment_uses_own_user "${environment}"; then
        stop_legacy_environment "${environment}"
        hand_over_tree "$(environment_dir "${environment}")" "${user}"
        hand_over_tree "$(environment_log_dir "${environment}")" "${user}"
        info "The files of ${environment} now belong to ${user}."
    fi

    secure_base_directory
    ensure_fpm_pool "${environment}"
    ensure_supervisor "${environment}"
    ensure_cron "${environment}"
    ensure_logrotate
    fpm_test_and_reload
    supervisorctl start "moashir-${environment}-horizon" >/dev/null 2>&1 || true
    artisan "${environment}" up || warn "artisan up failed; run it again as ${user}."
    verify_environment_user "${environment}"
}

remove_legacy_user() {
    local environment home leftover
    for environment in "${MOASHIR_ENVIRONMENTS[@]}"; do
        [[ -d "$(environment_dir "${environment}")" ]] || continue
        assert_environment_migrated "${environment}"
    done

    if ! id "${MOASHIR_LEGACY_USER}" >/dev/null 2>&1; then
        info "The shared user ${MOASHIR_LEGACY_USER} no longer exists."
        return
    fi

    [[ -z "$(pgrep -u "${MOASHIR_LEGACY_USER}" || true)" ]] \
        || die "Processes still run as ${MOASHIR_LEGACY_USER}; reload PHP-FPM (systemctl reload ${MOASHIR_FPM_SERVICE}) and try again."
    leftover="$(find "${MOASHIR_BASE}" "${MOASHIR_LOG}" -user "${MOASHIR_LEGACY_USER}" -print -quit 2>/dev/null || true)"
    [[ -z ${leftover} ]] || die "Files still belong to ${MOASHIR_LEGACY_USER}, for example ${leftover}."

    home="$(getent passwd "${MOASHIR_LEGACY_USER}" | cut -d: -f6)"
    userdel "${MOASHIR_LEGACY_USER}"
    if getent group "${MOASHIR_LEGACY_USER}" >/dev/null; then
        groupdel "${MOASHIR_LEGACY_USER}"
    fi
    if [[ ${home} == "/var/lib/${MOASHIR_LEGACY_USER}" ]]; then
        rm -rf "${home}"
    fi
    info "The shared user ${MOASHIR_LEGACY_USER} and its home were removed."
}
