#!/usr/bin/env bash
set -Eeuo pipefail
shopt -s inherit_errexit

source /opt/moashir/deploy/lib/common.sh
source /opt/moashir/deploy/lib/backup.sh
source /opt/moashir/deploy/lib/backup-target.sh

readonly ENVIRONMENT="${1:-}"

require_root
assert_environment "${ENVIRONMENT}"

WORK_DIR=""

cleanup() {
    backup_gnupg_stop
    if [[ -n ${WORK_DIR} ]]; then
        rm -rf "${WORK_DIR}"
    fi
}

count_copy_rows() {
    awk -v counts="$1" '
        { print }
        /^COPY [^ ]+ .*FROM stdin;$/ { table = $2; rows = 0; inside = 1; next }
        inside && $0 == "\\." { printf "%s %d\n", table, rows > counts; inside = 0; next }
        inside { rows++ }
    '
}

dump_database() {
    local output="$1"
    (cd / && sudo -u postgres pg_dump -p "$(environment_db_port "${ENVIRONMENT}")" --no-password --format=plain \
        "$(environment_database "${ENVIRONMENT}")") \
        | count_copy_rows "${output}/row-counts" \
        | gzip -6 \
        | backup_encrypt_to "${ENVIRONMENT}" "${output}/database.sql.gz.gpg"
}

archive_files() {
    local output="$1" storage app_name
    storage="$(environment_dir "${ENVIRONMENT}")/shared/storage/app"
    app_name="$(environment_env_value "${ENVIRONMENT}" APP_NAME)"

    {
        tar -C "${storage}" --exclude="./private/${app_name:-Moashir}" --exclude=./backup-temp \
            --index-file="${output}/file-index" -cvf - . || [[ $? -eq 1 ]]
    } | gzip -6 | backup_encrypt_to "${ENVIRONMENT}" "${output}/files.tar.gz.gpg"
}

encrypt_configuration() {
    backup_encrypt_to "${ENVIRONMENT}" "$1/config.env.gpg" <"$(environment_env_file "${ENVIRONMENT}")"
}

write_manifest() {
    local output="$1" set="$2" files
    files="$(grep -vc '/$' "${output}/file-index" || true)"

    {
        printf 'set %s\n' "${set}"
        printf 'environment %s\n' "${ENVIRONMENT}"
        printf 'created_at %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        printf 'database %s\n' "$(environment_database "${ENVIRONMENT}")"
        printf 'server_version %s\n' "$(environment_psql_admin "${ENVIRONMENT}" -At -d postgres -c 'SHOW server_version')"
        printf 'files %s\n' "${files}"
        sed 's/^/table /' "${output}/row-counts"
    } | backup_encrypt_to "${ENVIRONMENT}" "${output}/manifest.gpg"

    rm -f "${output}/row-counts" "${output}/file-index"
}

create_set() {
    local set="$1" spool partial
    spool="$(backup_spool_dir "${ENVIRONMENT}")"
    install -d -m 0700 -o root -g root "${MOASHIR_BACKUP_SPOOL}" "${spool}"
    partial="${spool}/${set}.partial"
    rm -rf "${partial}"
    install -d -m 0700 -o root -g root "${partial}"
    WORK_DIR="${partial}"

    dump_database "${partial}"
    archive_files "${partial}"
    encrypt_configuration "${partial}"
    write_manifest "${partial}" "${set}"
    backup_split_parts "${partial}" "$(backup_part_megabytes "${ENVIRONMENT}")"
    backup_write_checksums "${partial}"

    mv "${partial}" "${spool}/${set}"
    WORK_DIR=""
}

upload_set() {
    local set="$1" dir file
    dir="$(backup_spool_dir "${ENVIRONMENT}")/${set}"

    for file in "${dir}"/*; do
        [[ "$(basename "${file}")" != "${BACKUP_CHECKSUMS}" ]] || continue
        target_put "${ENVIRONMENT}" "${set}" "${file}"
    done

    target_put "${ENVIRONMENT}" "${set}" "${dir}/${BACKUP_CHECKSUMS}"
}

prune_target() {
    local newest="$1" cutoff sets set
    if [[ "$(backup_setting "${ENVIRONMENT}" BACKUP_TARGET_PRUNE true)" != "true" ]]; then
        info "Pruning is off (BACKUP_TARGET_PRUNE); the target's own retention rule applies."
        return
    fi

    cutoff=$(($(date -u +%s) - $(backup_retention_days "${ENVIRONMENT}") * 86400))
    sets="$(target_list_sets "${ENVIRONMENT}")"

    while IFS= read -r set; do
        [[ -n ${set} && ${set} != "${newest}" ]] || continue
        if [[ "$(backup_set_epoch "${set}")" -lt ${cutoff} ]]; then
            target_delete_set "${ENVIRONMENT}" "${set}"
            info "Removed ${set} from the off-server target (older than $(backup_retention_days "${ENVIRONMENT}") days)."
        fi
    done <<<"${sets}"
}

prune_local() {
    local spool set older
    spool="$(backup_spool_dir "${ENVIRONMENT}")"
    older="$(backup_local_sets "${ENVIRONMENT}" | head -n "-${BACKUP_LOCAL_KEEP}")"

    while IFS= read -r set; do
        [[ -n ${set} ]] || continue
        rm -rf "${spool:?}/${set}"
    done <<<"${older}"

    find "${spool}" -mindepth 1 -maxdepth 1 -type d -name '*.partial' -mmin +360 -exec rm -rf {} +
}

main() {
    local set
    [[ -s "$(backup_key_file "${ENVIRONMENT}")" ]] || die "The backup key $(backup_key_file "${ENVIRONMENT}") is missing; run provision.sh system."
    [[ -f "$(environment_env_file "${ENVIRONMENT}")" ]] || die "${ENVIRONMENT} is not installed on this server."

    backup_lock "${ENVIRONMENT}"
    trap cleanup EXIT
    backup_gnupg_start

    set="$(backup_new_set_name "${ENVIRONMENT}")"
    info "Creating the encrypted backup set ${set}."
    create_set "${set}"

    if backup_target_configured "${ENVIRONMENT}"; then
        target_require "${ENVIRONMENT}"
        upload_set "${set}"
        backup_record_state "${ENVIRONMENT}" last-backup "${set} $(date -u +%s) off-server"
        prune_target "${set}"
        info "Backup ${set} stored off-server ($(target_location "${ENVIRONMENT}"))."
    else
        backup_record_state "${ENVIRONMENT}" last-backup "${set} $(date -u +%s) local-only"
        warn "No off-server target is configured: ${set} stays on this server only. Fill in $(backup_target_file "${ENVIRONMENT}") (RUNBOOK: Backups)."
    fi

    prune_local
}

main
