#!/usr/bin/env bash
set -Eeuo pipefail

KIT_SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly KIT_SOURCE
source "${KIT_SOURCE}/lib/common.sh"
source "${KIT_SOURCE}/lib/backup.sh"
source "${KIT_SOURCE}/lib/provision-users.sh"
source "${KIT_SOURCE}/lib/provision-database.sh"
source "${KIT_SOURCE}/lib/provision-redis.sh"
source "${KIT_SOURCE}/lib/provision-backup.sh"
source "${KIT_SOURCE}/lib/provision-pdf.sh"
source "${KIT_SOURCE}/lib/provision-nginx.sh"
source "${KIT_SOURCE}/lib/provision-posture.sh"
source "${KIT_SOURCE}/lib/provision-postgresql.sh"
source "${KIT_SOURCE}/lib/provision-deploy-user.sh"

readonly COMPOSER_VERSION="2.11.1"
readonly SWAP_FILE="/swapfile"
readonly SWAP_SIZE="2G"
readonly EVIDENCE_UPLOAD_CEILING_MB=100
readonly CLAMAV_SIGNATURE_WAIT_SECONDS=900
readonly REQUIRED_PHP_MODULES=(pdo_pgsql redis mbstring dom curl zip intl pcntl posix fileinfo tokenizer gd)
readonly CLAMAV_PACKAGES=(clamav clamav-daemon clamav-freshclam)
readonly SYSTEM_PACKAGES=(gnupg redis-server)

FPM_NEEDS_RELOAD=0

usage() {
    cat <<'USAGE'
Usage: provision.sh <phase> [arguments]

New host, in this order (run as root on the server, each phase idempotent):
  postgresql                     PostgreSQL 17 from the PostgreSQL apt repository (key fingerprint verified);
                                 changes nothing when a PostgreSQL cluster already exists
  postgresql-repository          only add the verified PostgreSQL apt repository (existing-server upgrade path)
  system [env|all]               packages, swap, ClamAV, one system user per environment, directories, .env files,
                                 databases, one private Redis per environment, PHP-FPM pools, Horizon, scheduler,
                                 log rotation and archive, encrypted backup key and timers, PDF renderer
  nginx-bootstrap [env|all]      HTTP-only server blocks that answer the ACME challenge
  certificates [env|all]         Let's Encrypt certificates for the Moashir domains only
  nginx-final [env|all]          HTTPS server blocks (requires the certificates)
  nginx-default-tls              dedicated host only: default :443 server that rejects unknown names

Posture (MOASHIR_DEMO in the environment's .env):
  posture demo [env|all]         fictional data only; demo-install and the log mailer are allowed
  posture real [env|all]         real operation; refuses a database with demo records

Existing server (RUNBOOK: Applying Task-52 to an existing server):
  migrate-user <env>             move the environment from the shared moashir user to moashir-<env>
  redis-cutover <env>            move the environment from the shared Redis to its private instance
  redis-release-shared <env>     delete the environment's keys from the shared Redis after the checks
  remove-legacy-user             delete the shared moashir user once both environments are migrated

Other:
  deploy-user                    non-root deploy user for CI (MOASHIR_DEPLOY_PUBLIC_KEY_FILE=<public key file>)
  pdf-renderer [env|all]         pinned Chrome for Testing headless shell (monthly version bump)
  status [env|all]               read-only summary (no secrets)
USAGE
}

php_module_package() {
    case "$1" in
        pdo_pgsql) printf 'php%s-pgsql' "${MOASHIR_PHP_VERSION}" ;;
        redis) printf 'php%s-redis' "${MOASHIR_PHP_VERSION}" ;;
        mbstring) printf 'php%s-mbstring' "${MOASHIR_PHP_VERSION}" ;;
        dom) printf 'php%s-xml' "${MOASHIR_PHP_VERSION}" ;;
        curl) printf 'php%s-curl' "${MOASHIR_PHP_VERSION}" ;;
        zip) printf 'php%s-zip' "${MOASHIR_PHP_VERSION}" ;;
        intl) printf 'php%s-intl' "${MOASHIR_PHP_VERSION}" ;;
        gd) printf 'php%s-gd' "${MOASHIR_PHP_VERSION}" ;;
        *) printf 'php%s-common' "${MOASHIR_PHP_VERSION}" ;;
    esac
}

preflight_os() {
    require_root
    source /etc/os-release
    [[ ${ID:-} == "ubuntu" && ${VERSION_ID:-} == "24.04" ]] || die "Ubuntu 24.04 is expected."
}

preflight() {
    local command
    preflight_os
    for command in "${MOASHIR_PHP_BIN}" "${MOASHIR_FPM_BIN}" psql redis-cli nginx supervisorctl certbot openssl sudo curl sha256sum ldd flock; do
        command -v "${command}" >/dev/null || die "Missing command: ${command}"
    done
}

install_kit() {
    if [[ ${KIT_SOURCE} == "${MOASHIR_KIT}" ]]; then
        return
    fi

    install -d -m 0755 -o root -g root "${MOASHIR_OPT}" "${MOASHIR_KIT}"
    cp -R "${KIT_SOURCE}/." "${MOASHIR_KIT}/"
    chown -R root:root "${MOASHIR_KIT}"
    chmod -R go-w "${MOASHIR_KIT}"
    info "Deployment kit installed in ${MOASHIR_KIT} (root-owned)."
}

ensure_swap() {
    if [[ -n "$(swapon --show --noheadings)" ]]; then
        info "Swap already active; nothing changed."
        return
    fi

    if [[ ! -f ${SWAP_FILE} ]]; then
        fallocate -l "${SWAP_SIZE}" "${SWAP_FILE}"
        chmod 600 "${SWAP_FILE}"
        mkswap "${SWAP_FILE}" >/dev/null
    fi

    swapon "${SWAP_FILE}"
    grep -qsE "^${SWAP_FILE}[[:space:]]" /etc/fstab || printf '%s none swap sw 0 0\n' "${SWAP_FILE}" >>/etc/fstab
    info "Swap file ${SWAP_FILE} (${SWAP_SIZE}) active."
}

missing_packages() {
    local module package installed
    local -a missing=()
    installed="$("${MOASHIR_PHP_BIN}" -m)"

    for module in "${REQUIRED_PHP_MODULES[@]}"; do
        if ! grep -qix "${module}" <<<"${installed}"; then
            package="$(php_module_package "${module}")"
            missing+=("${package}")
        fi
    done

    for package in "${CLAMAV_PACKAGES[@]}" "${CHROME_PACKAGES[@]}" "${SYSTEM_PACKAGES[@]}"; do
        dpkg-query -W -f='${Status}' "${package}" 2>/dev/null | grep -q "install ok installed" || missing+=("${package}")
    done

    printf '%s\n' "${missing[@]}" | sort -u | sed '/^$/d'
}

ensure_packages() {
    local -a packages
    mapfile -t packages < <(missing_packages)

    if [[ ${#packages[@]} -eq 0 ]]; then
        info "Required packages already installed."
        return
    fi

    local policy="/usr/sbin/policy-rc.d" created_policy=0
    if [[ ! -e ${policy} ]]; then
        printf '#!/bin/sh\nexit 101\n' >"${policy}"
        chmod 755 "${policy}"
        created_policy=1
    fi

    info "Installing: ${packages[*]} (service restarts blocked during installation)."
    DEBIAN_FRONTEND=noninteractive apt-get update -q
    DEBIAN_FRONTEND=noninteractive apt-get install -y -q --no-install-recommends "${packages[@]}" || {
        [[ ${created_policy} -eq 1 ]] && rm -f "${policy}"
        die "Package installation failed."
    }

    [[ ${created_policy} -eq 1 ]] && rm -f "${policy}"
    FPM_NEEDS_RELOAD=1

    if [[ " ${packages[*]} " == *" redis-server "* ]]; then
        systemctl disable --now redis-server >/dev/null 2>&1 || true
        info "The default shared redis-server instance that came with the package is disabled; Moashir uses one private instance per environment."
    fi
}

ensure_composer() {
    if command -v composer >/dev/null; then
        info "Using the system Composer at $(command -v composer)."
        return
    fi

    if [[ -x "${MOASHIR_OPT}/bin/composer" ]]; then
        return
    fi

    local work expected actual
    work="$(mktemp -d)"
    curl -fsSL "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" -o "${work}/composer.phar"
    curl -fsSL "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar.sha256sum" -o "${work}/composer.phar.sha256sum"
    expected="$(cut -d' ' -f1 "${work}/composer.phar.sha256sum")"
    actual="$(sha256sum "${work}/composer.phar" | cut -d' ' -f1)"
    [[ -n ${expected} && ${expected} == "${actual}" ]] || die "Composer checksum mismatch."

    install -d -m 0755 "${MOASHIR_OPT}/bin"
    install -m 0755 -o root -g root "${work}/composer.phar" "${MOASHIR_OPT}/bin/composer"
    rm -rf "${work}"
    info "Composer ${COMPOSER_VERSION} installed in ${MOASHIR_OPT}/bin (checksum verified)."
}

ensure_directories() {
    local environment="$1" user dir log_dir
    user="$(environment_user "${environment}")"
    dir="$(environment_dir "${environment}")"
    log_dir="$(environment_log_dir "${environment}")"

    secure_base_directory
    install -d -m 0711 -o "${user}" -g "${user}" "${dir}" "${dir}/releases" "${dir}/shared"
    install -d -m 0750 -o "${user}" -g "${user}" \
        "${dir}/shared/storage" \
        "${dir}/shared/storage/app" \
        "${dir}/shared/storage/app/private" \
        "${dir}/shared/storage/app/public" \
        "${dir}/shared/storage/framework" \
        "${dir}/shared/storage/framework/cache" \
        "${dir}/shared/storage/framework/cache/data" \
        "${dir}/shared/storage/framework/sessions" \
        "${dir}/shared/storage/framework/testing" \
        "${dir}/shared/storage/framework/views" \
        "${dir}/shared/storage/logs"
    install -d -m 0700 -o "${user}" -g "${user}" "${dir}/shared/tmp"
    install -d -m 0755 -o root -g root "${MOASHIR_ACME_ROOT}"
    ensure_log_directories
    install -d -m 0750 -o "${user}" -g "${user}" "${log_dir}"
    install -d -m 0700 -o root -g root "${MOASHIR_ETC}" "${MOASHIR_ETC}/${environment}"
}

ensure_clamav() {
    local setting key changed=0
    while IFS= read -r setting; do
        [[ -z ${setting} ]] && continue
        key="${setting%% *}"
        if ! grep -qx "${setting}" /etc/clamav/clamd.conf; then
            sed -i "/^${key}[[:space:]]/d" /etc/clamav/clamd.conf
            printf '%s\n' "${setting}" >>/etc/clamav/clamd.conf
            changed=1
        fi
    done <"${MOASHIR_KIT}/clamav/clamd-settings.conf"

    local dropin_dir="/etc/systemd/system/clamav-daemon.socket.d"
    install -d -m 0755 "${dropin_dir}"
    if ! cmp -s "${MOASHIR_KIT}/clamav/clamav-daemon-socket.conf" "${dropin_dir}/moashir.conf"; then
        install -m 0644 "${MOASHIR_KIT}/clamav/clamav-daemon-socket.conf" "${dropin_dir}/moashir.conf"
        systemctl daemon-reload
        changed=1
    fi

    systemctl enable --now clamav-freshclam >/dev/null 2>&1 || die "clamav-freshclam could not start."
    wait_for_signatures

    systemctl enable clamav-daemon >/dev/null 2>&1
    if [[ ${changed} -eq 1 ]] || ! systemctl is-active --quiet clamav-daemon; then
        systemctl restart clamav-daemon.socket 2>/dev/null || true
        systemctl restart clamav-daemon
    fi
    info "ClamAV daemon active (socket /var/run/clamav/clamd.ctl, group clamav, mode 660)."
}

wait_for_signatures() {
    local waited=0
    until compgen -G "/var/lib/clamav/main.c[vl]d" >/dev/null && compgen -G "/var/lib/clamav/daily.c[vl]d" >/dev/null; do
        if [[ ${waited} -ge ${CLAMAV_SIGNATURE_WAIT_SECONDS} ]]; then
            die "ClamAV signatures were not downloaded within ${CLAMAV_SIGNATURE_WAIT_SECONDS} seconds; check: journalctl -u clamav-freshclam"
        fi
        sleep 15
        waited=$((waited + 15))
    done
}

ensure_env_file() {
    local environment="$1" env_file template line key user
    env_file="$(environment_env_file "${environment}")"
    template="${MOASHIR_KIT}/env/${environment}.env.template"
    user="$(environment_user "${environment}")"

    if [[ ! -f ${env_file} ]]; then
        install -m 600 -o "${user}" -g "${user}" "${template}" "${env_file}"
        env_set_key "${env_file}" APP_KEY "base64:$(openssl rand -base64 32)"
        env_set_key "${env_file}" BACKUP_ARCHIVE_PASSWORD "$(generate_secret)"
        info "Created ${env_file} from the template (real-operation posture) with a new APP_KEY."
    fi

    while IFS= read -r line || [[ -n ${line} ]]; do
        key="${line%%=*}"
        [[ -z ${key} ]] && continue
        if ! env_get_key "${env_file}" "${key}" >/dev/null; then
            env_set_key "${env_file}" "${key}" "${line#*=}"
            info "Added missing key ${key} to ${env_file}."
        fi
    done <"${template}"

    chown "${user}:${user}" "${env_file}"
    chmod 600 "${env_file}"
}

ensure_fpm_pool() {
    local environment="$1" rendered max_children
    rendered="$(mktemp)"
    max_children=3
    [[ ${environment} == "production" ]] && max_children=5

    render_template "${MOASHIR_KIT}/php-fpm/pool.conf.template" "${rendered}" \
        "ENVIRONMENT=${environment}" \
        "APP_USER=$(environment_user "${environment}")" \
        "WEB_GROUP=${MOASHIR_WEB_GROUP}" \
        "FPM_SOCKET=$(environment_fpm_socket "${environment}")" \
        "FPM_MAX_CHILDREN=${max_children}" \
        "LOG_DIR=$(environment_log_dir "${environment}")" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "UPLOAD_LIMIT_PHP=${EVIDENCE_UPLOAD_CEILING_MB}M" \
        "POST_LIMIT_PHP=$((EVIDENCE_UPLOAD_CEILING_MB + 1))M"

    if install_if_changed "${rendered}" "${MOASHIR_FPM_POOL_DIR}/moashir-${environment}.conf" 0644 root:root; then
        FPM_NEEDS_RELOAD=1
        info "PHP-FPM pool moashir-${environment} written (user $(environment_user "${environment}"))."
    fi
}

ensure_supervisor() {
    local environment="$1" rendered
    rendered="$(mktemp)"

    render_template "${MOASHIR_KIT}/supervisor/horizon.conf.template" "${rendered}" \
        "ENVIRONMENT=${environment}" \
        "PHP_BIN=${MOASHIR_PHP_BIN}" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "APP_USER=$(environment_user "${environment}")" \
        "APP_HOME=$(environment_home "${environment}")" \
        "LOG_DIR=$(environment_log_dir "${environment}")"

    if install_if_changed "${rendered}" "/etc/supervisor/conf.d/moashir-${environment}-horizon.conf" 0644 root:root; then
        supervisorctl reread >/dev/null
        supervisorctl update "moashir-${environment}-horizon" >/dev/null || true
        info "Supervisor program moashir-${environment}-horizon registered (user $(environment_user "${environment}"))."
    fi
}

ensure_cron() {
    local environment="$1" rendered
    rendered="$(mktemp)"

    render_template "${MOASHIR_KIT}/cron/scheduler.template" "${rendered}" \
        "APP_USER=$(environment_user "${environment}")" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "PHP_BIN=${MOASHIR_PHP_BIN}" \
        "LOG_DIR=$(environment_log_dir "${environment}")"

    install_if_changed "${rendered}" "/etc/cron.d/moashir-${environment}" 0644 root:root && info "Scheduler cron entry for ${environment} written." || true
}

provision_environment() {
    local environment="$1"
    info "Environment ${environment}"
    if [[ -d "$(environment_dir "${environment}")" ]]; then
        assert_environment_migrated "${environment}"
    fi

    ensure_env_user "${environment}"
    ensure_directories "${environment}"
    ensure_env_file "${environment}"
    ensure_database "${environment}"
    warn_if_old_postgresql "${environment}"
    ensure_redis_instance "${environment}"
    assert_redis_settings "${environment}"
    ensure_fpm_pool "${environment}"
    ensure_supervisor "${environment}"
    ensure_cron "${environment}"
    ensure_incoming "${environment}"
    provision_backups "${environment}"
}

pdf_renderer_phase() {
    local scope="$1"
    preflight
    install_kit
    ensure_packages
    provision_pdf_renderer "${scope}"
}

provision_system() {
    local scope="$1" environment
    FPM_NEEDS_RELOAD=0

    preflight
    install_kit
    ensure_swap
    ensure_packages
    ensure_composer
    ensure_clamav

    while IFS= read -r environment; do
        provision_environment "${environment}"
    done < <(environments_in_scope "${scope}")

    ensure_logrotate
    provision_pdf_renderer "${scope}"

    if [[ ${FPM_NEEDS_RELOAD} -eq 1 ]]; then
        fpm_test_and_reload
    fi

    info "System phase complete. Next on a new host: provision.sh nginx-bootstrap. Escrow the backup keys offline (RUNBOOK: Backups)."
}

status_line() {
    printf '  %-22s %s\n' "$1" "$2"
}

environment_status() {
    local environment="$1" env_file
    env_file="$(environment_env_file "${environment}")"
    printf '\n[%s] %s\n' "${environment}" "$(environment_domain "${environment}")"
    status_line "current release" "$(readlink "$(environment_dir "${environment}")/current" 2>/dev/null || printf 'none')"
    status_line "runs as" "$(environment_runtime_user "${environment}")"
    status_line ".env mode" "$(stat -c '%a %U' "${env_file}" 2>/dev/null || printf 'missing')"
    status_line "posture" "$(environment_is_demo "${environment}" && printf 'demo (fictional data)' || printf 'real operation')"
    status_line "Redis" "$(environment_env_value "${environment}" REDIS_HOST) ($(systemctl is-active "$(environment_redis_unit "${environment}")" 2>/dev/null || true))"
    status_line "FPM socket" "$([[ -S "$(environment_fpm_socket "${environment}")" ]] && printf present || printf missing)"
    status_line "Horizon" "$(supervisorctl status "moashir-${environment}-horizon" 2>/dev/null | awk '{print $2}' || true)"
    status_line "certificate" "$([[ -f "/etc/letsencrypt/live/$(environment_cert_name "${environment}")/fullchain.pem" ]] && printf present || printf missing)"
    status_line "database" "$(database_exists "${environment}" "$(environment_database "${environment}")" && printf present || printf missing)"
    status_line "PDF renderer" "$(environment_env_value "${environment}" PDF_RENDERER)"
    status_line "backup key" "$([[ -s "$(backup_key_file "${environment}")" ]] && printf present || printf missing)"
    status_line "off-server target" "$(backup_target_driver "${environment}" | grep . || printf 'NOT CONFIGURED')"
    status_line "last backup" "$(backup_read_state "${environment}" last-backup | awk '{ print $1, $3 }' | grep . || printf none)"
}

status() {
    local scope="$1" environment
    preflight

    printf 'Swap: %s\n' "$(swapon --show --noheadings | awk '{print $1, $3}' | paste -sd ' ' -)"
    printf 'ClamAV daemon: %s\n' "$(systemctl is-active clamav-daemon 2>/dev/null || true)"
    printf 'Freshclam: %s\n' "$(systemctl is-active clamav-freshclam 2>/dev/null || true)"
    printf 'chrome-headless-shell: %s\n' "$("${CHROME_ROOT}/current/${CHROME_BINARY_PATH}" --version 2>/dev/null || printf 'missing')"
    printf 'AppArmor profile: %s\n' "$([[ -f /etc/apparmor.d/${CHROME_APPARMOR_PROFILE} ]] && printf present || printf missing)"
    printf 'Shared user %s: %s\n' "${MOASHIR_LEGACY_USER}" "$(id "${MOASHIR_LEGACY_USER}" >/dev/null 2>&1 && printf 'still present' || printf 'removed')"
    printf 'Deploy user %s: %s\n' "${MOASHIR_DEPLOY_USER}" "$(id "${MOASHIR_DEPLOY_USER}" >/dev/null 2>&1 && printf present || printf 'not created')"

    while IFS= read -r environment; do
        environment_status "${environment}"
    done < <(environments_in_scope "${scope}")
}

with_kit() {
    preflight
    install_kit
    "$@"
}

main() {
    local phase="${1:-}"

    case "${phase}" in
        system) provision_system "${2:-all}" ;;
        postgresql) preflight_os && provision_postgresql ;;
        postgresql-repository) preflight_os && install_pgdg_repository ;;
        pdf-renderer) pdf_renderer_phase "${2:-all}" ;;
        nginx-bootstrap) nginx_bootstrap "${2:-all}" ;;
        certificates) certificates "${2:-all}" ;;
        nginx-final) nginx_final "${2:-all}" ;;
        nginx-default-tls) nginx_default_tls ;;
        posture) with_kit set_posture "${2:-}" "${3:-all}" ;;
        migrate-user) with_kit migrate_user "${2:-}" ;;
        redis-cutover) with_kit redis_cutover "${2:-}" ;;
        redis-release-shared) with_kit redis_release_shared "${2:-}" ;;
        remove-legacy-user) with_kit remove_legacy_user ;;
        deploy-user) with_kit provision_deploy_user ;;
        status) status "${2:-all}" ;;
        *)
            usage
            exit 2
            ;;
    esac
}

main "$@"
