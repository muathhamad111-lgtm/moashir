#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh
source /opt/moashir/deploy/lib/backup.sh

readonly ENVIRONMENT="${1:-}"
readonly KIB=1024
readonly MIB=$((1024 * 1024))

require_root
assert_environment "${ENVIRONMENT}"

DOMAIN="$(environment_domain "${ENVIRONMENT}")"
readonly DOMAIN
USER_NAME="$(environment_user "${ENVIRONMENT}")"
readonly USER_NAME
FAILURES=0
BODY_DIR=""

check() {
    local label="$1"
    shift

    if "$@" >/dev/null 2>&1; then
        printf '  ok    %s\n' "${label}"
    else
        printf '  FAIL  %s\n' "${label}"
        FAILURES=$((FAILURES + 1))
    fi
}

advise() {
    local label="$1"
    shift

    if "$@" >/dev/null 2>&1; then
        printf '  ok    %s\n' "${label}"
    else
        printf '  warn  %s\n' "${label}"
    fi
}

local_https() {
    curl -sS --max-time 30 --resolve "${DOMAIN}:443:127.0.0.1" "$@"
}

status_is() {
    local expected="$1"
    shift
    [[ "$(local_https -o /dev/null -w '%{http_code}' "$@")" == "${expected}" ]]
}

header_present() {
    local header="$1" path="$2" headers
    headers="$(local_https -o /dev/null -D - "https://${DOMAIN}${path}")"
    grep -qi "^${header}:" <<<"${headers}"
}

body_of() {
    local bytes="$1" file="${BODY_DIR}/${1}.bin"
    [[ -f ${file} ]] || head -c "${bytes}" /dev/zero >"${file}"
    printf '%s' "${file}"
}

upload_passes_nginx() {
    local path="$1" bytes="$2"
    [[ "$(local_https -o /dev/null -w '%{http_code}' -X POST -H 'Accept: application/json' \
        -H 'Content-Type: application/octet-stream' --data-binary "@$(body_of "${bytes}")" "https://${DOMAIN}${path}")" != "413" ]]
}

upload_refused_as_json() {
    local path="$1" bytes="$2" response
    response="$(local_https -X POST -H 'Accept: application/json' -H 'Accept-Language: en' -H "Content-Length: ${bytes}" \
        -w '\n%{http_code}' "https://${DOMAIN}${path}" || true)"
    [[ ${response##*$'\n'} == "413" && ${response} == *'"code":"request.too_large"'* ]]
}

env_mode_is_private() {
    [[ "$(stat -c '%a %U' "$(environment_env_file "${ENVIRONMENT}")")" == "600 ${USER_NAME}" ]]
}

debug_is_off() {
    [[ "$(environment_env_value "${ENVIRONMENT}" APP_DEBUG)" == "false" ]]
}

posture_is_consistent() {
    environment_is_demo "${ENVIRONMENT}" || [[ -z "$(posture_violations "${ENVIRONMENT}")" ]]
}

horizon_runs_as_environment_user() {
    local pid attempt
    for attempt in 1 2 3 4 5 6; do
        if supervisorctl status "moashir-${ENVIRONMENT}-horizon" | grep -q RUNNING; then
            pid="$(supervisorctl pid "moashir-${ENVIRONMENT}-horizon")"
            [[ "$(ps -o user= -p "${pid}" | tr -d ' ')" == "${USER_NAME}" ]]
            return
        fi
        sleep 5
    done
    return 1
}

fpm_workers_run_as_environment_user() {
    local users
    local_https -o /dev/null -H 'Accept: application/json' "https://${DOMAIN}/api/v1/content/prices"
    users="$(pgrep -f "^php-fpm: pool moashir-${ENVIRONMENT}\$" | xargs -r ps -o user= -p | tr -d ' ' | sort -u)"
    [[ ${users} == "${USER_NAME}" ]]
}

redis_uses_own_socket() {
    [[ "$(environment_env_value "${ENVIRONMENT}" REDIS_HOST)" == "$(environment_redis_socket "${ENVIRONMENT}")" \
        && "$(environment_env_value "${ENVIRONMENT}" REDIS_PORT)" == "0" ]]
}

redis_socket_is_private() {
    [[ "$(stat -c '%a %U %G' "$(environment_redis_socket "${ENVIRONMENT}")")" == "660 ${USER_NAME} ${USER_NAME}" ]]
}

redis_has_no_tcp_listener() {
    [[ "$(redis-cli -s "$(environment_redis_socket "${ENVIRONMENT}")" CONFIG GET port | tail -n 1)" == "0" ]]
}

backup_key_is_root_only() {
    [[ "$(stat -c '%a %U' "$(backup_key_file "${ENVIRONMENT}")")" == "400 root" ]]
}

backup_is_recent() {
    local age
    age="$(backup_last_age_hours "${ENVIRONMENT}")" || return 1
    [[ ${age} -le ${BACKUP_MAX_AGE_HOURS} ]]
}

pdf_renderer_enabled() {
    [[ "$(environment_env_value "${ENVIRONMENT}" PDF_RENDERER)" == "chromium" ]]
}

pdf_is_tagged_with_embedded_fonts() {
    local file="$1" unembedded
    pdfinfo "${file}" | grep -Eq '^Tagged:[[:space:]]+yes' || return 1
    unembedded="$(pdffonts "${file}" | tail -n +3 | awk '$(NF-4) != "yes"' | wc -l)"
    [[ ${unembedded} -eq 0 ]]
}

pdf_samples_render() {
    local output file status=0
    output="$(mktemp -d "$(environment_dir "${ENVIRONMENT}")/shared/tmp/pdf-smoke.XXXXXX")"
    chown "$(environment_runtime_user "${ENVIRONMENT}"):$(environment_runtime_user "${ENVIRONMENT}")" "${output}"

    if artisan "${ENVIRONMENT}" moashir:documents:render-samples --output="${output}"; then
        for file in "${output}"/*.pdf; do
            pdf_is_tagged_with_embedded_fonts "${file}" || status=1
        done
    else
        status=1
    fi

    rm -rf "${output}"
    return "${status}"
}

clamd_answers() {
    [[ "$(printf 'zPING\0' | timeout 5 socat - UNIX-CONNECT:/var/run/clamav/clamd.ctl 2>/dev/null | tr -d '\0')" == "PONG" ]]
}

check_http() {
    check "SPA index answers 200" status_is 200 "https://${DOMAIN}/"
    check "SPA deep link falls back to index (200)" status_is 200 "https://${DOMAIN}/admin/settings"
    check "SPA sends Content-Security-Policy" header_present Content-Security-Policy /
    check "SPA sends Strict-Transport-Security" header_present Strict-Transport-Security /
    check "CSRF cookie endpoint answers 204" status_is 204 "https://${DOMAIN}/sanctum/csrf-cookie"
    check "public prices endpoint answers 200" status_is 200 -H 'Accept: application/json' "https://${DOMAIN}/api/v1/content/prices"
    check "session endpoint refuses a guest (401)" status_is 401 -H 'Accept: application/json' "https://${DOMAIN}/api/v1/auth/session"
    check "API sends Strict-Transport-Security" header_present Strict-Transport-Security /api/v1/content/prices
    check "HTTP redirects to HTTPS (301)" bash -c "[[ \$(curl -sS -o /dev/null -w '%{http_code}' --resolve ${DOMAIN}:80:127.0.0.1 http://${DOMAIN}/) == 301 ]]"
}

check_upload_limits() {
    BODY_DIR="$(mktemp -d)"
    check "evidence upload route lets 3 MiB reach Laravel" upload_passes_nginx /api/v1/assessments/smoke/evidence $((3 * MIB))
    check "refund evidence route lets 3 MiB reach Laravel" upload_passes_nginx /api/v1/finance/payments/smoke/refunds $((3 * MIB))
    check "evidence upload route refuses 102 MiB with a JSON 413" upload_refused_as_json /api/v1/assessments/smoke/evidence $((102 * MIB))
    check "CMS image route lets 5 MiB + 64 KiB reach Laravel" upload_passes_nginx /api/v1/admin/content/images $((5 * MIB + 64 * KIB))
    check "KPI image route lets 5 MiB + 64 KiB reach Laravel" upload_passes_nginx /api/v1/admin/kpis/images $((5 * MIB + 64 * KIB))
    check "CMS image route refuses 6 MiB with a JSON 413" upload_refused_as_json /api/v1/admin/content/images $((6 * MIB))
    check "entity logo route lets 2 MiB + 64 KiB reach Laravel" upload_passes_nginx /api/v1/entities/me/logo $((2 * MIB + 64 * KIB))
    check "entity logo route refuses 3 MiB with a JSON 413" upload_refused_as_json /api/v1/entities/me/logo $((3 * MIB))
    check "branding logo route lets 1 MiB + 64 KiB reach Laravel" upload_passes_nginx /api/v1/settings/branding/logo $((1 * MIB + 64 * KIB))
    check "branding logo route refuses 2 MiB with a JSON 413" upload_refused_as_json /api/v1/settings/branding/logo $((2 * MIB))
    check "other API routes refuse 3 MiB with a JSON 413" upload_refused_as_json /api/v1/auth/login $((3 * MIB))
    rm -rf "${BODY_DIR}"
}

check_isolation() {
    check ".env is mode 600 and owned by ${USER_NAME}" env_mode_is_private
    check "APP_DEBUG is false" debug_is_off
    check "posture: $(environment_is_demo "${ENVIRONMENT}" && printf 'demo, fictional data only' || printf 'real operation, .env ready')" posture_is_consistent
    check "Horizon runs as ${USER_NAME}" horizon_runs_as_environment_user
    check "PHP-FPM workers run as ${USER_NAME}" fpm_workers_run_as_environment_user
    check "PHP-FPM pool socket exists" test -S "$(environment_fpm_socket "${ENVIRONMENT}")"
    check "scheduler cron entry exists" test -f "/etc/cron.d/moashir-${ENVIRONMENT}"
    check "Redis: .env points at the environment's own socket" redis_uses_own_socket
    check "Redis: socket is 660 and owned by ${USER_NAME}" redis_socket_is_private
    check "Redis: no TCP listener" redis_has_no_tcp_listener
}

check_services() {
    check "ClamAV daemon is active" systemctl is-active --quiet clamav-daemon
    if command -v socat >/dev/null; then
        check "ClamAV answers PING on its socket" clamd_answers
    fi
    if pdf_renderer_enabled; then
        check "PDF renderer prints the sample documents (tagged, fonts embedded)" pdf_samples_render
    else
        printf '  skip  PDF renderer (PDF_RENDERER is not chromium)\n'
    fi
}

check_backups() {
    check "backup encryption key is root-only (400)" backup_key_is_root_only
    check "daily backup timer is enabled" systemctl is-enabled --quiet "moashir-backup@${ENVIRONMENT}.timer"
    advise "off-server backup target configured" backup_target_configured "${ENVIRONMENT}"
    advise "a backup completed within ${BACKUP_MAX_AGE_HOURS} hours" backup_is_recent
}

main() {
    printf 'Smoke checks for %s (%s)\n' "${ENVIRONMENT}" "${DOMAIN}"
    check_http
    check_upload_limits
    check_isolation
    check_services
    check_backups

    if [[ ${FAILURES} -gt 0 ]]; then
        die "${FAILURES} smoke check(s) failed."
    fi

    info "All smoke checks passed (warnings above need the operator)."
}

main
