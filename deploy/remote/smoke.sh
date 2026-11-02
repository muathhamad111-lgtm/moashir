#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ENVIRONMENT="${1:-}"

require_root
assert_environment "${ENVIRONMENT}"

DOMAIN="$(environment_domain "${ENVIRONMENT}")"
readonly DOMAIN
FAILURES=0

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

local_https() {
    curl -sS --max-time 15 --resolve "${DOMAIN}:443:127.0.0.1" "$@"
}

status_is() {
    local expected="$1"
    shift
    [[ "$(local_https -o /dev/null -w '%{http_code}' "$@")" == "${expected}" ]]
}

header_present() {
    local header="$1" path="$2"
    local_https -o /dev/null -D - "https://${DOMAIN}${path}" | grep -qi "^${header}:"
}

env_mode_is_private() {
    [[ "$(stat -c '%a %U' "$(environment_env_file "${ENVIRONMENT}")")" == "600 ${MOASHIR_APP_USER}" ]]
}

debug_is_off() {
    [[ "$(env_get_key "$(environment_env_file "${ENVIRONMENT}")" APP_DEBUG)" == "false" ]]
}

horizon_running() {
    supervisorctl status "moashir-${ENVIRONMENT}-horizon" | grep -q RUNNING
}

clamd_answers() {
    [[ "$(printf 'zPING\0' | timeout 5 socat - UNIX-CONNECT:/var/run/clamav/clamd.ctl 2>/dev/null | tr -d '\0')" == "PONG" ]]
}

main() {
    printf 'Smoke checks for %s (%s)\n' "${ENVIRONMENT}" "${DOMAIN}"
    check "SPA index answers 200" status_is 200 "https://${DOMAIN}/"
    check "SPA deep link falls back to index (200)" status_is 200 "https://${DOMAIN}/admin/settings"
    check "SPA sends Content-Security-Policy" header_present Content-Security-Policy /
    check "SPA sends Strict-Transport-Security" header_present Strict-Transport-Security /
    check "CSRF cookie endpoint answers 204" status_is 204 "https://${DOMAIN}/sanctum/csrf-cookie"
    check "public prices endpoint answers 200" status_is 200 -H 'Accept: application/json' "https://${DOMAIN}/api/v1/content/prices"
    check "session endpoint refuses a guest (401)" status_is 401 -H 'Accept: application/json' "https://${DOMAIN}/api/v1/auth/session"
    check "API sends Strict-Transport-Security" header_present Strict-Transport-Security /api/v1/content/prices
    check "HTTP redirects to HTTPS (301)" bash -c "[[ \$(curl -sS -o /dev/null -w '%{http_code}' --resolve ${DOMAIN}:80:127.0.0.1 http://${DOMAIN}/) == 301 ]]"
    check ".env is mode 600 and owned by ${MOASHIR_APP_USER}" env_mode_is_private
    check "APP_DEBUG is false" debug_is_off
    check "Horizon is running" horizon_running
    check "PHP-FPM pool socket exists" test -S "$(environment_fpm_socket "${ENVIRONMENT}")"
    check "scheduler cron entry exists" test -f "/etc/cron.d/moashir-${ENVIRONMENT}"
    check "ClamAV daemon is active" systemctl is-active --quiet clamav-daemon
    if command -v socat >/dev/null; then
        check "ClamAV answers PING on its socket" clamd_answers
    fi

    if [[ ${FAILURES} -gt 0 ]]; then
        die "${FAILURES} smoke check(s) failed."
    fi

    info "All smoke checks passed."
}

main
