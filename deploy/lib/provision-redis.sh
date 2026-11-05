#!/usr/bin/env bash

readonly REDIS_SERVER_BIN="/usr/bin/redis-server"
readonly REDIS_UNIT_TEMPLATE="moashir-redis@.service"
readonly REDIS_MIN_MAJOR=7
readonly REDIS_WAIT_SECONDS=30
readonly HORIZON_WAIT_SECONDS=60

redis_maxmemory() {
    case "$1" in
        production) printf '256mb' ;;
        *) printf '128mb' ;;
    esac
}

redis_conf_file() {
    printf '%s/%s.conf' "${MOASHIR_REDIS_ETC}" "$1"
}

redis_data_dir() {
    printf '/var/lib/moashir-redis-%s' "$1"
}

redis_cutover_backup() {
    printf '%s/%s/env.before-redis-cutover' "${MOASHIR_ETC}" "$1"
}

socket_redis_cli() {
    local environment="$1"
    shift
    redis-cli -s "$(environment_redis_socket "${environment}")" "$@"
}

install_systemd_units() {
    local unit copy changed=1
    for unit in "$@"; do
        copy="$(mktemp)"
        cp "${MOASHIR_KIT}/systemd/${unit}" "${copy}"
        if install_if_changed "${copy}" "/etc/systemd/system/${unit}" 0644 root:root; then
            changed=0
        fi
    done

    if [[ ${changed} -eq 0 ]]; then
        systemctl daemon-reload
    fi
    return "${changed}"
}

assert_redis_server() {
    local version major
    [[ -x ${REDIS_SERVER_BIN} ]] || die "${REDIS_SERVER_BIN} is missing (Ubuntu package redis-server)."
    version="$("${REDIS_SERVER_BIN}" --version | sed -n 's/.* v=\([0-9.]*\).*/\1/p')"
    major="${version%%.*}"
    [[ ${major} =~ ^[0-9]+$ && ${major} -ge ${REDIS_MIN_MAJOR} ]] || die "Redis ${REDIS_MIN_MAJOR} or newer is required (found ${version:-unknown})."
}

wait_for_redis() {
    local environment="$1" waited=0
    until [[ "$(socket_redis_cli "${environment}" ping 2>/dev/null)" == "PONG" ]]; do
        [[ ${waited} -lt ${REDIS_WAIT_SECONDS} ]] || die "Redis of ${environment} does not answer on $(environment_redis_socket "${environment}"); see journalctl -u $(environment_redis_unit "${environment}")."
        sleep 1
        waited=$((waited + 1))
    done
}

assert_redis_private() {
    local environment="$1" user port
    user="$(environment_user "${environment}")"
    [[ "$(stat -c '%a %U %G' "$(environment_redis_socket "${environment}")")" == "660 ${user} ${user}" ]] \
        || die "The Redis socket of ${environment} must be mode 660, owner and group ${user}."
    port="$(socket_redis_cli "${environment}" CONFIG GET port | tail -n 1)"
    [[ ${port} == "0" ]] || die "Redis of ${environment} listens on TCP port ${port}; it must use the unix socket only."
}

ensure_redis_instance() {
    local environment="$1" user rendered unit restart=0
    user="$(environment_user "${environment}")"
    unit="$(environment_redis_unit "${environment}")"
    assert_redis_server
    id "${user}" >/dev/null 2>&1 || die "The system user ${user} does not exist yet."

    install -d -m 0755 -o root -g root "${MOASHIR_REDIS_ETC}"
    rendered="$(mktemp)"
    render_template "${MOASHIR_KIT}/redis/redis.conf.template" "${rendered}" \
        "SOCKET=$(environment_redis_socket "${environment}")" \
        "DATA_DIR=$(redis_data_dir "${environment}")" \
        "MAXMEMORY=$(redis_maxmemory "${environment}")"
    if install_if_changed "${rendered}" "$(redis_conf_file "${environment}")" 0640 "root:${user}"; then
        restart=1
    fi
    if install_systemd_units "${REDIS_UNIT_TEMPLATE}"; then
        restart=1
    fi

    systemctl enable "${unit}" >/dev/null 2>&1
    if [[ ${restart} -eq 1 ]] && systemctl is-active --quiet "${unit}"; then
        systemctl restart "${unit}"
    else
        systemctl start "${unit}"
    fi

    wait_for_redis "${environment}"
    assert_redis_private "${environment}"
    info "Redis of ${environment}: ${unit}, socket $(environment_redis_socket "${environment}") (660, ${user}), no TCP."
}

environment_uses_own_redis() {
    [[ "$(environment_env_value "$1" REDIS_HOST)" == "$(environment_redis_socket "$1")" && "$(environment_env_value "$1" REDIS_PORT)" == "0" ]]
}

assert_redis_settings() {
    environment_uses_own_redis "$1" \
        || die "${1} still uses the shared Redis ($(environment_env_value "$1" REDIS_HOST)). Run provision.sh redis-cutover ${1} (RUNBOOK: Applying Task-52 to an existing server)."
}

copy_redis_keys() {
    local environment="$1" legacy="$2"
    REDIS_SOURCE_HOST="$(env_get_key "${legacy}" REDIS_HOST || true)" \
        REDIS_SOURCE_PORT="$(env_get_key "${legacy}" REDIS_PORT || true)" \
        REDIS_SOURCE_PASSWORD="$(env_get_key "${legacy}" REDIS_PASSWORD || true)" \
        REDIS_TARGET_SOCKET="$(environment_redis_socket "${environment}")" \
        REDIS_KEY_PREFIX="moashir_${environment}_" \
        "${MOASHIR_PHP_BIN}" "${MOASHIR_KIT}/bin/redis-copy.php" \
        "$(env_get_key "${legacy}" REDIS_DB):0" "$(env_get_key "${legacy}" REDIS_CACHE_DB):1"
}

point_environment_at_own_redis() {
    local env_file
    env_file="$(environment_env_file "$1")"
    env_set_key "${env_file}" REDIS_HOST "$(environment_redis_socket "$1")"
    env_set_key "${env_file}" REDIS_PORT 0
    env_set_key "${env_file}" REDIS_PASSWORD ""
    env_set_key "${env_file}" REDIS_DB 0
    env_set_key "${env_file}" REDIS_CACHE_DB 1
}

wait_for_horizon() {
    local environment="$1" waited=0
    until artisan "${environment}" horizon:status 2>/dev/null | grep -qi 'running'; do
        [[ ${waited} -lt ${HORIZON_WAIT_SECONDS} ]] || die "Horizon of ${environment} did not report running within ${HORIZON_WAIT_SECONDS} s."
        sleep 3
        waited=$((waited + 3))
    done
}

REDIS_CUTOVER_ENVIRONMENT=""

redis_cutover_rollback() {
    local environment="${REDIS_CUTOVER_ENVIRONMENT}" user
    [[ -n ${environment} ]] || return 0
    user="$(environment_user "${environment}")"
    warn "Redis cutover of ${environment} failed; restoring the previous .env."
    install -m 600 -o "${user}" -g "${user}" "$(redis_cutover_backup "${environment}")" "$(environment_env_file "${environment}")" || true
    artisan "${environment}" config:cache || true
    fpm_test_and_reload || true
    supervisorctl start "moashir-${environment}-horizon" >/dev/null 2>&1 || true
    artisan "${environment}" up || true
}

redis_cutover() {
    local environment="$1" backup
    assert_environment "${environment}"
    assert_environment_migrated "${environment}"
    ensure_redis_instance "${environment}"

    if environment_uses_own_redis "${environment}"; then
        info "${environment} already uses its own Redis."
        return
    fi

    backup="$(redis_cutover_backup "${environment}")"
    install -m 600 -o root -g root "$(environment_env_file "${environment}")" "${backup}"
    REDIS_CUTOVER_ENVIRONMENT="${environment}"
    trap redis_cutover_rollback EXIT

    artisan "${environment}" down --retry=60
    supervisorctl stop "moashir-${environment}-horizon" >/dev/null 2>&1 || true
    copy_redis_keys "${environment}" "${backup}"
    point_environment_at_own_redis "${environment}"
    artisan "${environment}" config:cache
    fpm_test_and_reload
    supervisorctl start "moashir-${environment}-horizon" >/dev/null
    artisan "${environment}" up
    wait_for_horizon "${environment}"

    REDIS_CUTOVER_ENVIRONMENT=""
    trap - EXIT
    info "${environment} now uses its own Redis. The old keys stay in the shared instance as a rollback point; remove them after the checks with provision.sh redis-release-shared ${environment}."
}

legacy_redis_cli() {
    local legacy="$1"
    shift
    REDISCLI_AUTH="$(env_get_key "${legacy}" REDIS_PASSWORD || true)" redis-cli --no-auth-warning \
        -h "$(env_get_key "${legacy}" REDIS_HOST)" -p "$(env_get_key "${legacy}" REDIS_PORT)" "$@"
}

unlink_legacy_keys() {
    local environment="$1" legacy="$2" database="$3"
    (
        REDISCLI_AUTH="$(env_get_key "${legacy}" REDIS_PASSWORD || true)"
        export REDISCLI_AUTH
        redis-cli --no-auth-warning -h "$(env_get_key "${legacy}" REDIS_HOST)" -p "$(env_get_key "${legacy}" REDIS_PORT)" \
            -n "${database}" --scan --pattern "moashir_${environment}_*" \
            | xargs -r -n 200 redis-cli --no-auth-warning -h "$(env_get_key "${legacy}" REDIS_HOST)" \
                -p "$(env_get_key "${legacy}" REDIS_PORT)" -n "${database}" UNLINK >/dev/null
    )
}

redis_release_shared() {
    local environment="$1" legacy database key remaining
    assert_environment "${environment}"
    legacy="$(redis_cutover_backup "${environment}")"
    [[ -f ${legacy} ]] || die "No record of a Redis cutover for ${environment} (${legacy})."
    assert_redis_settings "${environment}"

    for key in REDIS_DB REDIS_CACHE_DB; do
        database="$(env_get_key "${legacy}" "${key}")"
        unlink_legacy_keys "${environment}" "${legacy}" "${database}"
        remaining="$(legacy_redis_cli "${legacy}" -n "${database}" --scan --pattern "moashir_${environment}_*" | grep -c . || true)"
        [[ ${remaining} -eq 0 ]] || die "${remaining} key(s) of ${environment} remain in shared Redis database ${database}."
        info "Shared Redis database ${database}: every moashir_${environment}_ key removed; other keys untouched."
    done

    shred -u "${legacy}"
    info "The saved pre-cutover .env of ${environment} was shredded."
}
