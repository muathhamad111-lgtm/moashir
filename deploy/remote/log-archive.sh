#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ENVIRONMENT="${1:-}"
readonly ARCHIVE_RETENTION_DAYS=400

require_root
assert_environment "${ENVIRONMENT}"

archive_closed_logs() {
    local source="$1" destination="$2" user="$3" today name day target
    today="$(date -u +%F)"

    while IFS= read -r name; do
        day="${name#laravel-}"
        day="${day%.log}"
        [[ ${day} =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ && ${day} < "${today}" ]] || continue
        target="${destination}/laravel-${day}.log.gz"
        [[ -e ${target} ]] && continue
        sudo -u "${user}" -- cat "${source}/${name}" | gzip -9 >"${target}.partial"
        chmod 0600 "${target}.partial"
        mv -f "${target}.partial" "${target}"
    done < <(find "${source}" -maxdepth 1 -type f -name 'laravel-*.log' -printf '%f\n' | LC_ALL=C sort)
}

main() {
    local source destination
    source="$(environment_dir "${ENVIRONMENT}")/shared/storage/logs"
    destination="${MOASHIR_LOG_ARCHIVE}/${ENVIRONMENT}/application"
    [[ -d ${source} ]] || die "${source} does not exist."

    install -d -m 0700 -o root -g root "${MOASHIR_LOG_ARCHIVE}" "${MOASHIR_LOG_ARCHIVE}/${ENVIRONMENT}" "${destination}"
    archive_closed_logs "${source}" "${destination}" "$(environment_runtime_user "${ENVIRONMENT}")"
    find "${destination}" -type f -name 'laravel-*.log.gz' -mtime "+${ARCHIVE_RETENTION_DAYS}" -delete
    info "Closed application logs of ${ENVIRONMENT} archived in ${destination} (root only, ${ARCHIVE_RETENTION_DAYS} days)."
}

main
