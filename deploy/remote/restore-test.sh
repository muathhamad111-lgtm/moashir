#!/usr/bin/env bash
set -Eeuo pipefail
shopt -s inherit_errexit

source /opt/moashir/deploy/lib/common.sh
source /opt/moashir/deploy/lib/backup.sh
source /opt/moashir/deploy/lib/backup-target.sh

readonly ENVIRONMENT="${1:-}"
readonly TABLE_PATTERN='^[A-Za-z0-9_."]+$'

require_root
assert_environment "${ENVIRONMENT}"

WORK_DIR=""
TEST_DATABASE=""
SET_NAME=""
ORIGIN=""
FAILURES=0

cleanup() {
    if [[ -n ${TEST_DATABASE} ]]; then
        environment_psql_admin "${ENVIRONMENT}" -d postgres -c "DROP DATABASE IF EXISTS \"${TEST_DATABASE}\"" >/dev/null 2>&1 \
            || warn "Drop the throw-away database ${TEST_DATABASE} manually."
    fi
    backup_gnupg_stop
    if [[ -n ${WORK_DIR} ]]; then
        rm -rf "${WORK_DIR}"
    fi
}

result() {
    local label="$1" outcome="$2"
    printf '  %-5s %s\n' "${outcome}" "${label}"
    if [[ ${outcome} == "FAIL" ]]; then
        FAILURES=$((FAILURES + 1))
    fi
}

newest_complete_remote_set() {
    local sets set
    sets="$(target_list_sets "${ENVIRONMENT}" | LC_ALL=C sort -r)"
    while IFS= read -r set; do
        [[ -n ${set} ]] || continue
        if target_set_is_complete "${ENVIRONMENT}" "${set}"; then
            printf '%s' "${set}"
            return
        fi
    done <<<"${sets}"
}

fetch_remote_set() {
    local files name
    files="$(target_list_files "${ENVIRONMENT}" "${SET_NAME}")"
    while IFS= read -r name; do
        [[ -n ${name} ]] || continue
        target_get "${ENVIRONMENT}" "${SET_NAME}" "${name}" "${WORK_DIR}/${name}"
    done <<<"${files}"
}

select_set() {
    if backup_target_configured "${ENVIRONMENT}"; then
        target_require "${ENVIRONMENT}"
        SET_NAME="$(newest_complete_remote_set)"
        [[ -n ${SET_NAME} ]] || die "No complete backup set of ${ENVIRONMENT} exists on the off-server target."
        ORIGIN="off-server ($(target_location "${ENVIRONMENT}"))"
        fetch_remote_set
        return
    fi

    SET_NAME="$(backup_local_sets "${ENVIRONMENT}" | tail -n 1)"
    [[ -n ${SET_NAME} ]] || die "No backup set of ${ENVIRONMENT} exists yet; run deploy.sh ${ENVIRONMENT} backup-run first."
    ORIGIN="LOCAL ONLY - no off-server target is configured"
    warn "Testing the local copy only; this does not prove an off-server recovery."
    cp -a "$(backup_spool_dir "${ENVIRONMENT}")/${SET_NAME}/." "${WORK_DIR}/"
}

manifest_value() {
    awk -v key="$1" '$1 == key { sub(/^[^ ]+ /, ""); print; exit }' "${WORK_DIR}/manifest"
}

expected_counts() {
    awk '$1 == "table" { print $2, $3 }' "${WORK_DIR}/manifest" | LC_ALL=C sort
}

count_query() {
    local table
    while read -r table _; do
        [[ ${table} =~ ${TABLE_PATTERN} ]] || die "Unexpected table name in the manifest of ${SET_NAME}."
        printf "SELECT '%s', count(*) FROM ONLY %s;\n" "${table}" "${table}"
    done <<<"$(expected_counts)"
}

restore_database() {
    TEST_DATABASE="moashir_restore_test_${ENVIRONMENT}_$(date -u +%Y%m%d%H%M%S)"
    environment_psql_admin "${ENVIRONMENT}" -d postgres -c "CREATE DATABASE \"${TEST_DATABASE}\" ENCODING 'UTF8' TEMPLATE template0"
    environment_psql_admin "${ENVIRONMENT}" -d postgres -c "REVOKE ALL ON DATABASE \"${TEST_DATABASE}\" FROM PUBLIC"
    backup_decrypt "${ENVIRONMENT}" "${WORK_DIR}/database.sql.gz.gpg" | gunzip \
        | environment_psql_admin "${ENVIRONMENT}" -d "${TEST_DATABASE}" >/dev/null
}

compare_row_counts() {
    local actual mismatches tables rows
    count_query >"${WORK_DIR}/count.sql"
    actual="$(environment_psql_admin "${ENVIRONMENT}" -At -F ' ' -d "${TEST_DATABASE}" <"${WORK_DIR}/count.sql" | LC_ALL=C sort)"
    mismatches="$(diff <(expected_counts) <(printf '%s\n' "${actual}") | grep -c '^[<>]' || true)"
    tables="$(expected_counts | grep -c . || true)"
    rows="$(expected_counts | awk '{ total += $2 } END { print total + 0 }')"

    if [[ ${tables} -gt 0 && ${mismatches} -eq 0 ]]; then
        result "database restored into a throw-away database: ${tables} tables, ${rows} rows, every row count matches" ok
    else
        result "database row counts: ${mismatches} difference(s) over ${tables} tables" FAIL
    fi
}

verify_files() {
    local expected actual
    expected="$(manifest_value files)"

    if ! backup_decrypt "${ENVIRONMENT}" "${WORK_DIR}/files.tar.gz.gpg" | gunzip | tar -tf - >"${WORK_DIR}/file-list"; then
        result "file archive could not be decrypted or read" FAIL
        return
    fi

    actual="$(grep -vc '/$' "${WORK_DIR}/file-list" || true)"
    if [[ ${actual} == "${expected}" ]]; then
        result "file archive readable: ${actual} files, as recorded" ok
    else
        result "file archive holds ${actual} files, the manifest records ${expected}" FAIL
    fi
}

verify_configuration() {
    local keys
    keys="$(backup_decrypt "${ENVIRONMENT}" "${WORK_DIR}/config.env.gpg" | grep -c '^APP_KEY=base64:' || true)"
    if [[ ${keys} -ge 1 ]]; then
        result "environment file with APP_KEY restorable (value not shown)" ok
    else
        result "environment file without APP_KEY: encrypted columns could not be read after a recovery" FAIL
    fi
}

main() {
    local started outcome
    started="$(date -u +%s)"
    [[ -s "$(backup_key_file "${ENVIRONMENT}")" ]] || die "The backup key $(backup_key_file "${ENVIRONMENT}") is missing."

    backup_lock "${ENVIRONMENT}"
    trap cleanup EXIT
    install -d -m 0700 -o root -g root "${MOASHIR_BACKUP_SPOOL}"
    WORK_DIR="$(mktemp -d "${MOASHIR_BACKUP_SPOOL}/restore-test.XXXXXX")"
    chmod 0700 "${WORK_DIR}"
    backup_gnupg_start

    select_set
    printf 'Restore test of %s\n  set     %s\n  origin  %s\n' "${ENVIRONMENT}" "${SET_NAME}" "${ORIGIN}"

    backup_verify_checksums "${WORK_DIR}" || die "Checksum mismatch in ${SET_NAME}; the set is damaged."
    result "checksums of every encrypted part" ok
    backup_join_parts "${WORK_DIR}"
    backup_decrypt "${ENVIRONMENT}" "${WORK_DIR}/manifest.gpg" >"${WORK_DIR}/manifest"
    printf '  taken   %s (PostgreSQL %s)\n' "$(manifest_value created_at)" "$(manifest_value server_version)"

    restore_database
    compare_row_counts
    verify_files
    verify_configuration

    outcome="PASS"
    [[ ${FAILURES} -eq 0 ]] || outcome="FAIL"
    backup_record_state "${ENVIRONMENT}" last-restore-test "${SET_NAME} $(date -u +%s) ${outcome}"
    printf 'Restore test %s in %s s (record it in the restore drill log).\n' "${outcome}" "$(($(date -u +%s) - started))"
    [[ ${outcome} == "PASS" ]] || exit 1
}

main
