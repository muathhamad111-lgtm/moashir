#!/usr/bin/env bash
set -Eeuo pipefail
shopt -s inherit_errexit

source /opt/moashir/deploy/lib/common.sh
source /opt/moashir/deploy/lib/backup.sh
source /opt/moashir/deploy/lib/backup-target.sh

readonly ENVIRONMENT="${1:-}"

require_root
assert_environment "${ENVIRONMENT}"

ERRORS=0

line() {
    printf '  %-5s %s\n' "$1" "$2"
    if [[ $1 == "FAIL" ]]; then
        ERRORS=$((ERRORS + 1))
    fi
}

check_key() {
    local key
    key="$(backup_key_file "${ENVIRONMENT}")"
    if [[ "$(stat -c '%a %U' "${key}" 2>/dev/null)" == "400 root" ]]; then
        line ok "encryption key ${key} (root only, fingerprint $(backup_key_fingerprint "${ENVIRONMENT}")); compare with the escrowed copy"
    else
        line FAIL "encryption key ${key} is missing or not root-only 400; run provision.sh system"
    fi
}

check_schedule() {
    local timer="moashir-backup@${ENVIRONMENT}.timer" next
    if systemctl is-enabled --quiet "${timer}" 2>/dev/null; then
        next="$(systemctl show "${timer}" -p NextElapseUSecRealtime --value 2>/dev/null || true)"
        line ok "daily timer ${timer} enabled (next run ${next:-unknown})"
    else
        line FAIL "daily timer ${timer} is not enabled; run provision.sh system"
    fi
}

check_last_backup() {
    local state age
    state="$(backup_read_state "${ENVIRONMENT}" last-backup)"
    age="$(backup_last_age_hours "${ENVIRONMENT}" || true)"
    if [[ -z ${state} || -z ${age} ]]; then
        line warn "no backup has completed yet; run deploy.sh ${ENVIRONMENT} backup-run"
    elif [[ ${age} -gt ${BACKUP_MAX_AGE_HOURS} ]]; then
        line warn "last backup $(awk '{ print $1, "(" $3 ")" }' <<<"${state}") is ${age} hours old"
    else
        line ok "last backup $(awk '{ print $1, "(" $3 ")" }' <<<"${state}"), ${age} hours ago"
    fi
}

check_target() {
    local sets count newest
    if ! backup_target_configured "${ENVIRONMENT}"; then
        line warn "NO OFF-SERVER TARGET: fill in $(backup_target_file "${ENVIRONMENT}") (S3-compatible or SFTP, inside the Kingdom)"
        return
    fi

    target_require "${ENVIRONMENT}"
    sets="$(target_list_sets "${ENVIRONMENT}" | LC_ALL=C sort)"
    count="$(grep -c . <<<"${sets}" || true)"
    newest="$(tail -n 1 <<<"${sets}")"
    line ok "off-server target $(target_location "${ENVIRONMENT}"): ${count} set(s), newest ${newest:-none}, retention $(backup_retention_days "${ENVIRONMENT}") days"
}

check_restore_test() {
    local state epoch days
    state="$(backup_read_state "${ENVIRONMENT}" last-restore-test)"
    epoch="$(awk '{ print $2 }' <<<"${state}")"
    if [[ ! ${epoch} =~ ^[0-9]+$ ]]; then
        line warn "no restore test recorded; run deploy.sh ${ENVIRONMENT} restore-test"
        return
    fi

    days=$((($(date -u +%s) - epoch) / 86400))
    if [[ ${days} -gt ${BACKUP_RESTORE_TEST_MAX_AGE_DAYS} || ${state} != *PASS ]]; then
        line warn "last restore test $(awk '{ print $1, $3 }' <<<"${state}"), ${days} days ago (drill at least quarterly)"
    else
        line ok "last restore test $(awk '{ print $1, $3 }' <<<"${state}"), ${days} days ago"
    fi
}

main() {
    printf 'Backup check for %s\n' "${ENVIRONMENT}"
    check_key
    check_schedule
    check_last_backup
    check_target
    check_restore_test
    [[ ${ERRORS} -eq 0 ]] || die "${ERRORS} backup check(s) failed."
}

main
