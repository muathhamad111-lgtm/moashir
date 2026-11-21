#!/usr/bin/env bash

readonly BACKUP_RETENTION_DEFAULT_DAYS=30
readonly BACKUP_PART_DEFAULT_MEGABYTES=1024
readonly BACKUP_LOCAL_KEEP=1
readonly BACKUP_MAX_AGE_HOURS=26
readonly BACKUP_RESTORE_TEST_MAX_AGE_DAYS=92
readonly BACKUP_CHECKSUMS="checksums.sha256"

BACKUP_GNUPG_HOME=""

backup_key_file() {
    printf '%s/%s/backup.key' "${MOASHIR_ETC}" "$1"
}

backup_target_file() {
    printf '%s/%s/backup-target.env' "${MOASHIR_ETC}" "$1"
}

backup_spool_dir() {
    printf '%s/%s' "${MOASHIR_BACKUP_SPOOL}" "$1"
}

backup_state_file() {
    printf '%s/%s/%s' "${MOASHIR_STATE}" "$1" "$2"
}

backup_lock() {
    exec 9>"/run/lock/moashir-backup-$1.lock"
    flock -n 9 || die "Another backup or restore test of $1 is running."
}

backup_setting() {
    local file value=""
    file="$(backup_target_file "$1")"
    [[ -f ${file} ]] && value="$(env_get_key "${file}" "$2" || true)"
    printf '%s' "${value:-${3:-}}"
}

backup_target_driver() {
    backup_setting "$1" BACKUP_TARGET_DRIVER
}

backup_target_configured() {
    case "$(backup_target_driver "$1")" in
        s3 | sftp) return 0 ;;
        *) return 1 ;;
    esac
}

backup_retention_days() {
    local days
    days="$(backup_setting "$1" BACKUP_RETENTION_DAYS "${BACKUP_RETENTION_DEFAULT_DAYS}")"
    [[ ${days} =~ ^[0-9]+$ && ${days} -ge 1 ]] || die "BACKUP_RETENTION_DAYS must be a positive whole number of days."
    printf '%s' "${days}"
}

backup_part_megabytes() {
    local megabytes
    megabytes="$(backup_setting "$1" BACKUP_PART_MEGABYTES "${BACKUP_PART_DEFAULT_MEGABYTES}")"
    [[ ${megabytes} =~ ^[0-9]+$ && ${megabytes} -ge 16 ]] || die "BACKUP_PART_MEGABYTES must be a whole number of at least 16."
    printf '%s' "${megabytes}"
}

backup_new_set_name() {
    printf '%s-%s' "$1" "$(date -u +%Y%m%dT%H%M%SZ)"
}

backup_is_set_name() {
    [[ $2 =~ ^$1-[0-9]{8}T[0-9]{6}Z$ ]]
}

backup_set_epoch() {
    local stamp="${1##*-}"
    date -u -d "${stamp:0:4}-${stamp:4:2}-${stamp:6:2} ${stamp:9:2}:${stamp:11:2}:${stamp:13:2}" +%s
}

backup_key_fingerprint() {
    sha256sum "$(backup_key_file "$1")" | cut -c1-16
}

backup_gnupg_start() {
    BACKUP_GNUPG_HOME="$(mktemp -d)"
    chmod 0700 "${BACKUP_GNUPG_HOME}"
}

backup_gnupg_stop() {
    [[ -n ${BACKUP_GNUPG_HOME} && -d ${BACKUP_GNUPG_HOME} ]] || return 0
    gpgconf --homedir "${BACKUP_GNUPG_HOME}" --kill gpg-agent >/dev/null 2>&1 || true
    rm -rf "${BACKUP_GNUPG_HOME}"
    BACKUP_GNUPG_HOME=""
}

backup_gpg() {
    local environment="$1"
    shift
    gpg --homedir "${BACKUP_GNUPG_HOME}" --batch --quiet --no-tty --pinentry-mode loopback \
        --passphrase-file "$(backup_key_file "${environment}")" "$@"
}

backup_encrypt_to() {
    backup_gpg "$1" --symmetric --cipher-algo AES256 --force-ocb --s2k-digest-algo SHA512 --compress-algo none --output "$2"
}

backup_decrypt() {
    backup_gpg "$1" --decrypt "$2"
}

backup_split_parts() {
    local dir="$1" megabytes="$2" file
    for file in "${dir}"/*.gpg; do
        [[ -f ${file} ]] || continue
        if [[ "$(stat -c '%s' "${file}")" -gt $((megabytes * 1024 * 1024)) ]]; then
            split -b "${megabytes}M" -d -a 3 "${file}" "${file}.part-"
            rm -f "${file}"
        fi
    done
}

backup_join_parts() {
    local dir="$1" first base
    for first in "${dir}"/*.part-000; do
        [[ -f ${first} ]] || continue
        base="${first%.part-000}"
        cat "${base}".part-* >"${base}"
        rm -f "${base}".part-*
    done
}

backup_write_checksums() {
    (cd "$1" && find . -maxdepth 1 -type f ! -name "${BACKUP_CHECKSUMS}" -printf '%f\n' | LC_ALL=C sort | xargs -r sha256sum --) >"$1/${BACKUP_CHECKSUMS}"
}

backup_verify_checksums() {
    (cd "$1" && sha256sum --quiet --strict --check "${BACKUP_CHECKSUMS}")
}

backup_local_sets() {
    local environment="$1" spool name
    spool="$(backup_spool_dir "${environment}")"
    [[ -d ${spool} ]] || return 0

    while IFS= read -r name; do
        if backup_is_set_name "${environment}" "${name}"; then
            printf '%s\n' "${name}"
        fi
    done < <(find "${spool}" -mindepth 1 -maxdepth 1 -type d -printf '%f\n' | LC_ALL=C sort)
}

backup_record_state() {
    local environment="$1" name="$2" line="$3" file
    install -d -m 0700 -o root -g root "${MOASHIR_STATE}" "${MOASHIR_STATE}/${environment}"
    file="$(backup_state_file "${environment}" "${name}")"
    printf '%s\n' "${line}" >"${file}.next"
    mv -f "${file}.next" "${file}"
}

backup_read_state() {
    local file
    file="$(backup_state_file "$1" "$2")"
    [[ -f ${file} ]] && head -n 1 "${file}"
    return 0
}

backup_last_age_hours() {
    local line epoch
    line="$(backup_read_state "$1" last-backup)"
    epoch="$(awk '{ print $2 }' <<<"${line}")"
    [[ ${epoch} =~ ^[0-9]+$ ]] || return 1
    printf '%s' $((($(date -u +%s) - epoch) / 3600))
}
