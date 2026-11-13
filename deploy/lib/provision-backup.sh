#!/usr/bin/env bash

readonly BACKUP_SYSTEMD_UNITS=(moashir-backup@.service moashir-backup@.timer moashir-log-archive@.service moashir-log-archive@.timer)

ensure_backup_key() {
    local key
    key="$(backup_key_file "$1")"

    if [[ ! -s ${key} ]]; then
        (
            umask 077
            openssl rand -base64 48 >"${key}"
        )
        info "Backup encryption key created in ${key} (root only). Escrow a copy OFFLINE now (RUNBOOK: Backups); without it no backup can be restored."
    fi

    chown root:root "${key}"
    chmod 0400 "${key}"
}

ensure_backup_target_file() {
    local file
    file="$(backup_target_file "$1")"

    if [[ ! -f ${file} ]]; then
        install -m 0600 -o root -g root "${MOASHIR_KIT}/backup/backup-target.env.template" "${file}"
        info "Off-server backup target settings written to ${file}; fill them in (RUNBOOK: Backups)."
    fi

    chown root:root "${file}"
    chmod 0600 "${file}"
}

ensure_backup_schedule() {
    local environment="$1"
    install_systemd_units "${BACKUP_SYSTEMD_UNITS[@]}" || true
    systemctl enable --now "moashir-backup@${environment}.timer" "moashir-log-archive@${environment}.timer" >/dev/null 2>&1 \
        || die "The backup and log archive timers of ${environment} could not be enabled."
    info "Daily encrypted backup and log archive timers of ${environment} enabled."
}

provision_backups() {
    local environment="$1"
    install -d -m 0700 -o root -g root "${MOASHIR_BACKUP_SPOOL}" "$(backup_spool_dir "${environment}")" \
        "${MOASHIR_STATE}" "${MOASHIR_STATE}/${environment}"
    ensure_backup_key "${environment}"
    ensure_backup_target_file "${environment}"
    ensure_backup_schedule "${environment}"
}

ensure_log_directories() {
    install -d -m 0755 -o root -g root "${MOASHIR_LOG}"
    install -d -m 0750 -o root -g "${MOASHIR_WEB_GROUP}" "${MOASHIR_NGINX_LOG}"
    install -d -m 0700 -o root -g root "${MOASHIR_LOG_ARCHIVE}" "${MOASHIR_LOG_ARCHIVE}/nginx"
}

ensure_logrotate() {
    local copy rendered environment
    ensure_log_directories
    copy="$(mktemp)"
    cp "${MOASHIR_KIT}/logrotate/moashir-nginx" "${copy}"
    install_if_changed "${copy}" /etc/logrotate.d/moashir-nginx 0644 root:root && info "Log rotation for Nginx logs (400 days, root-only archive) written." || true

    for environment in "${MOASHIR_ENVIRONMENTS[@]}"; do
        [[ -d "$(environment_log_dir "${environment}")" ]] || continue
        rendered="$(mktemp)"
        render_template "${MOASHIR_KIT}/logrotate/moashir-environment.template" "${rendered}" \
            "LOG_DIR=$(environment_log_dir "${environment}")" \
            "APP_USER=$(environment_runtime_user "${environment}")"
        install_if_changed "${rendered}" "/etc/logrotate.d/moashir-${environment}" 0644 root:root && info "Log rotation for ${environment} written." || true
    done

    rm -f /etc/logrotate.d/moashir
}

archive_legacy_nginx_logs() {
    local file moved=0
    install -d -m 0700 -o root -g root "${MOASHIR_LOG_ARCHIVE}/nginx/legacy"
    for file in /var/log/nginx/moashir-*.log*; do
        [[ -f ${file} ]] || continue
        mv "${file}" "${MOASHIR_LOG_ARCHIVE}/nginx/legacy/"
        moved=1
    done
    if [[ ${moved} -eq 1 ]]; then
        info "Previous Moashir Nginx logs moved to ${MOASHIR_LOG_ARCHIVE}/nginx/legacy (root only)."
    fi
}
