#!/usr/bin/env bash
set -Eeuo pipefail

KIT_SOURCE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly KIT_SOURCE
source "${KIT_SOURCE}/lib/common.sh"

readonly COMPOSER_VERSION="2.11.1"
readonly SWAP_FILE="/swapfile"
readonly SWAP_SIZE="2G"
readonly UPLOAD_LIMIT="25m"
readonly UPLOAD_LIMIT_PHP="25M"
readonly POST_LIMIT_PHP="26M"
readonly CLAMAV_SIGNATURE_WAIT_SECONDS=900
readonly REQUIRED_PHP_MODULES=(pdo_pgsql redis mbstring dom curl zip intl pcntl posix fileinfo tokenizer)
readonly CLAMAV_PACKAGES=(clamav clamav-daemon clamav-freshclam)

usage() {
    cat <<'USAGE'
Usage: provision.sh <phase> [staging|production|all]

Phases (run as root on the server, in this order, each one idempotent):
  system           packages, swap, ClamAV, system user, directories, PHP-FPM pools, databases,
                   .env files, Horizon programs, scheduler cron entries, log rotation
  nginx-bootstrap  HTTP-only server blocks that answer the ACME challenge for the Moashir domains
  certificates     Let's Encrypt certificates for the Moashir domains only (webroot, no Nginx edits)
  nginx-final      HTTPS server blocks (requires the certificates)
  status           read-only summary (no secrets)
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
        *) printf 'php%s-common' "${MOASHIR_PHP_VERSION}" ;;
    esac
}

preflight() {
    require_root
    source /etc/os-release
    [[ ${ID:-} == "ubuntu" && ${VERSION_ID:-} == "24.04" ]] || die "Ubuntu 24.04 is expected."

    local command
    for command in "${MOASHIR_PHP_BIN}" "${MOASHIR_FPM_BIN}" psql redis-cli nginx supervisorctl certbot openssl sudo curl sha256sum; do
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

    for package in "${CLAMAV_PACKAGES[@]}"; do
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

ensure_app_user() {
    if ! id "${MOASHIR_APP_USER}" >/dev/null 2>&1; then
        useradd --system --user-group --home-dir "${MOASHIR_HOME}" --create-home --shell /usr/sbin/nologin "${MOASHIR_APP_USER}"
        info "System user ${MOASHIR_APP_USER} created."
    fi

    chmod 0750 "${MOASHIR_HOME}"

    if getent group clamav >/dev/null && ! id -nG "${MOASHIR_APP_USER}" | grep -qw clamav; then
        usermod -aG clamav "${MOASHIR_APP_USER}"
        FPM_NEEDS_RELOAD=1
        info "${MOASHIR_APP_USER} may now reach the ClamAV socket."
    fi
}

ensure_directories() {
    local environment="$1" dir log_dir
    dir="$(environment_dir "${environment}")"
    log_dir="$(environment_log_dir "${environment}")"

    install -d -m 0711 -o "${MOASHIR_APP_USER}" -g "${MOASHIR_APP_USER}" "${MOASHIR_BASE}" "${dir}" "${dir}/releases" "${dir}/shared"
    install -d -m 0750 -o "${MOASHIR_APP_USER}" -g "${MOASHIR_APP_USER}" \
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
    install -d -m 0700 -o "${MOASHIR_APP_USER}" -g "${MOASHIR_APP_USER}" "${dir}/shared/tmp"
    install -d -m 0755 -o root -g root "${MOASHIR_ACME_ROOT}"
    install -d -m 0755 -o root -g root "${MOASHIR_LOG}"
    install -d -m 0750 -o "${MOASHIR_APP_USER}" -g "${MOASHIR_APP_USER}" "${log_dir}"
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

generate_secret() {
    openssl rand -hex 32
}

set_role_password() {
    local role="$1" password="$2" verifier
    verifier="$(printf '%s' "${password}" | "${MOASHIR_PHP_BIN}" "${MOASHIR_KIT}/bin/pg-scram-verifier.php")"
    printf 'ALTER ROLE "%s" PASSWORD %s;\n' "${role}" "'${verifier}'" | psql_admin -d postgres
}

role_exists() {
    [[ "$(psql_admin -At -d postgres -c "SELECT 1 FROM pg_roles WHERE rolname = '$1'")" == "1" ]]
}

database_exists() {
    [[ "$(psql_admin -At -d postgres -c "SELECT 1 FROM pg_database WHERE datname = '$1'")" == "1" ]]
}

ensure_role() {
    local role="$1" connection_limit="$2"

    if ! role_exists "${role}"; then
        psql_admin -d postgres -c "CREATE ROLE \"${role}\" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION"
    fi

    psql_admin -d postgres -c "ALTER ROLE \"${role}\" CONNECTION LIMIT ${connection_limit}"
}

ensure_owner_secret() {
    local environment="$1" owner secrets password
    owner="$(environment_role "${environment}" owner)"
    secrets="$(environment_owner_secrets "${environment}")"

    if [[ -s ${secrets} ]]; then
        return
    fi

    password="$(generate_secret)"
    set_role_password "${owner}" "${password}"
    (
        umask 077
        printf 'DB_USERNAME=%s\nDB_PASSWORD=%s\n' "${owner}" "${password}" >"${secrets}"
    )
    chmod 600 "${secrets}"
    info "Owner role password generated and stored in ${secrets} (root only)."
}

ensure_app_secret() {
    local environment="$1" app env_file password current
    app="$(environment_role "${environment}" app)"
    env_file="$(environment_env_file "${environment}")"
    current="$(env_get_key "${env_file}" DB_PASSWORD || true)"

    if [[ -n ${current} ]]; then
        return
    fi

    password="$(generate_secret)"
    set_role_password "${app}" "${password}"
    env_set_key "${env_file}" DB_PASSWORD "${password}"
    info "Application role password generated and stored in ${env_file} (mode 600)."
}

ensure_database() {
    local environment="$1" database owner app maintenance
    database="$(environment_database "${environment}")"
    owner="$(environment_role "${environment}" owner)"
    app="$(environment_role "${environment}" app)"
    maintenance="$(environment_role "${environment}" maintenance)"

    ensure_role "${owner}" 5
    ensure_role "${app}" 40
    ensure_role "${maintenance}" 2

    if ! database_exists "${database}"; then
        psql_admin -d postgres -c "CREATE DATABASE \"${database}\" OWNER \"${owner}\" ENCODING 'UTF8' TEMPLATE template0"
        info "Database ${database} created (owner ${owner})."
    fi

    psql_admin -d postgres -c "REVOKE ALL ON DATABASE \"${database}\" FROM PUBLIC"
    psql_admin -d postgres -c "GRANT CONNECT ON DATABASE \"${database}\" TO \"${owner}\", \"${app}\", \"${maintenance}\""

    ensure_owner_secret "${environment}"
    ensure_app_secret "${environment}"
}

redis_password() {
    local line
    [[ -r /etc/redis/redis.conf ]] || return 0

    while IFS= read -r line; do
        if [[ ${line} =~ ^requirepass[[:space:]]+(.+)$ ]]; then
            printf '%s' "${BASH_REMATCH[1]}"
            return 0
        fi
    done </etc/redis/redis.conf
}

ensure_env_file() {
    local environment="$1" env_file template line key password
    env_file="$(environment_env_file "${environment}")"
    template="${MOASHIR_KIT}/env/${environment}.env.template"

    if [[ ! -f ${env_file} ]]; then
        install -m 600 -o "${MOASHIR_APP_USER}" -g "${MOASHIR_APP_USER}" "${template}" "${env_file}"
        env_set_key "${env_file}" APP_KEY "base64:$(openssl rand -base64 32)"
        env_set_key "${env_file}" BACKUP_ARCHIVE_PASSWORD "$(generate_secret)"
        password="$(redis_password)"
        [[ -n ${password} ]] && env_set_key "${env_file}" REDIS_PASSWORD "${password}"
        info "Created ${env_file} from the template with a new APP_KEY and backup password."
    fi

    while IFS= read -r line || [[ -n ${line} ]]; do
        key="${line%%=*}"
        [[ -z ${key} ]] && continue
        if ! env_get_key "${env_file}" "${key}" >/dev/null; then
            env_set_key "${env_file}" "${key}" "${line#*=}"
            info "Added missing key ${key} to ${env_file}."
        fi
    done <"${template}"

    chown "${MOASHIR_APP_USER}:${MOASHIR_APP_USER}" "${env_file}"
    chmod 600 "${env_file}"
}

redis_cli() {
    local environment="$1"
    shift
    local password
    password="$(env_get_key "$(environment_env_file "${environment}")" REDIS_PASSWORD || true)"
    REDISCLI_AUTH="${password}" redis-cli -h 127.0.0.1 -p 6379 --no-auth-warning "$@"
}

ensure_redis_isolation() {
    local environment="$1" env_file database foreign
    env_file="$(environment_env_file "${environment}")"

    [[ "$(redis_cli "${environment}" ping 2>&1)" == "PONG" ]] || die "Redis does not answer PONG for ${environment}; if it requires a password set REDIS_PASSWORD in ${env_file}."

    for key in REDIS_DB REDIS_CACHE_DB; do
        database="$(env_get_key "${env_file}" "${key}")"
        redis_cli "${environment}" -n "${database}" ping >/dev/null || die "Redis database ${database} is not available."
        foreign="$(redis_cli "${environment}" -n "${database}" --scan --count 1000 | grep -v "^moashir_${environment}_" | head -n 1 || true)"
        [[ -z ${foreign} ]] || die "Redis database ${database} already holds keys of another application; choose free indexes for ${key} in ${env_file}."
    done
    info "Redis databases of ${environment} are free or used by Moashir ${environment} only."
}

ensure_fpm_pool() {
    local environment="$1" rendered max_children
    rendered="$(mktemp)"
    max_children=3
    [[ ${environment} == "production" ]] && max_children=5

    render_template "${MOASHIR_KIT}/php-fpm/pool.conf.template" "${rendered}" \
        "ENVIRONMENT=${environment}" \
        "APP_USER=${MOASHIR_APP_USER}" \
        "WEB_GROUP=${MOASHIR_WEB_GROUP}" \
        "FPM_SOCKET=$(environment_fpm_socket "${environment}")" \
        "FPM_MAX_CHILDREN=${max_children}" \
        "LOG_DIR=$(environment_log_dir "${environment}")" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "UPLOAD_LIMIT_PHP=${UPLOAD_LIMIT_PHP}" \
        "POST_LIMIT_PHP=${POST_LIMIT_PHP}"

    if install_if_changed "${rendered}" "${MOASHIR_FPM_POOL_DIR}/moashir-${environment}.conf" 0644 root:root; then
        FPM_NEEDS_RELOAD=1
        info "PHP-FPM pool moashir-${environment} written."
    fi
}

ensure_supervisor() {
    local environment="$1" rendered
    rendered="$(mktemp)"

    render_template "${MOASHIR_KIT}/supervisor/horizon.conf.template" "${rendered}" \
        "ENVIRONMENT=${environment}" \
        "PHP_BIN=${MOASHIR_PHP_BIN}" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "APP_USER=${MOASHIR_APP_USER}" \
        "APP_HOME=${MOASHIR_HOME}" \
        "LOG_DIR=$(environment_log_dir "${environment}")"

    if install_if_changed "${rendered}" "/etc/supervisor/conf.d/moashir-${environment}-horizon.conf" 0644 root:root; then
        supervisorctl reread >/dev/null
        supervisorctl update "moashir-${environment}-horizon" >/dev/null || true
        info "Supervisor program moashir-${environment}-horizon registered (it starts after the first deployment)."
    fi
}

ensure_cron() {
    local environment="$1" rendered
    rendered="$(mktemp)"

    render_template "${MOASHIR_KIT}/cron/scheduler.template" "${rendered}" \
        "APP_USER=${MOASHIR_APP_USER}" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "PHP_BIN=${MOASHIR_PHP_BIN}" \
        "LOG_DIR=$(environment_log_dir "${environment}")"

    install_if_changed "${rendered}" "/etc/cron.d/moashir-${environment}" 0644 root:root && info "Scheduler cron entry for ${environment} written." || true
}

ensure_logrotate() {
    local copy
    copy="$(mktemp)"
    cp "${MOASHIR_KIT}/logrotate/moashir" "${copy}"
    install_if_changed "${copy}" /etc/logrotate.d/moashir 0644 root:root && info "Log rotation for /var/log/moashir written." || true
}

provision_system() {
    local scope="$1" environment
    FPM_NEEDS_RELOAD=0

    preflight
    install_kit
    ensure_swap
    ensure_packages
    ensure_composer
    ensure_app_user
    ensure_clamav
    ensure_logrotate

    while IFS= read -r environment; do
        info "Environment ${environment}"
        ensure_directories "${environment}"
        ensure_env_file "${environment}"
        ensure_database "${environment}"
        ensure_redis_isolation "${environment}"
        ensure_fpm_pool "${environment}"
        ensure_supervisor "${environment}"
        ensure_cron "${environment}"
    done < <(environments_in_scope "${scope}")

    if [[ ${FPM_NEEDS_RELOAD} -eq 1 ]]; then
        fpm_test_and_reload
    fi

    info "System phase complete. Next: provision.sh nginx-bootstrap"
}

install_nginx_shared() {
    local copy changed=1
    copy="$(mktemp)"
    cp "${MOASHIR_KIT}/nginx/moashir-http.conf" "${copy}"
    install_if_changed "${copy}" /etc/nginx/conf.d/moashir-http.conf 0644 root:root || changed=0
    return "$((1 - changed))"
}

install_site() {
    local environment="$1" template="$2" rendered available enabled
    rendered="$(mktemp)"
    available="/etc/nginx/sites-available/moashir-${environment}.conf"
    enabled="/etc/nginx/sites-enabled/moashir-${environment}.conf"

    render_template "${template}" "${rendered}" \
        "DOMAIN=$(environment_domain "${environment}")" \
        "ENVIRONMENT=${environment}" \
        "ACME_ROOT=${MOASHIR_ACME_ROOT}" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "CERT_NAME=$(environment_cert_name "${environment}")" \
        "UPLOAD_LIMIT=${UPLOAD_LIMIT}"

    install_if_changed "${rendered}" "${available}" 0644 root:root || true
    ln -sfn "${available}" "${enabled}"
}

nginx_bootstrap() {
    local scope="$1" environment
    preflight
    grep -Rqs "sites-enabled" /etc/nginx/nginx.conf || die "/etc/nginx/nginx.conf does not include sites-enabled."
    install_nginx_shared || true

    while IFS= read -r environment; do
        if [[ -f "/etc/letsencrypt/live/$(environment_cert_name "${environment}")/fullchain.pem" ]]; then
            info "Certificate for ${environment} already exists; keeping the final server block."
            continue
        fi
        install_site "${environment}" "${MOASHIR_KIT}/nginx/site-bootstrap.conf.template"
    done < <(environments_in_scope "${scope}")

    nginx_test_and_reload
    info "Next: provision.sh certificates"
}

certificates() {
    local scope="$1" environment domain
    local -a account_options=()
    preflight

    if [[ -n ${MOASHIR_CERTBOT_EMAIL:-} ]]; then
        account_options+=(--email "${MOASHIR_CERTBOT_EMAIL}")
    else
        account_options+=(--register-unsafely-without-email)
    fi

    if [[ -n ${MOASHIR_CERTBOT_ACCOUNT:-} ]]; then
        account_options+=(--account "${MOASHIR_CERTBOT_ACCOUNT}")
    fi

    while IFS= read -r environment; do
        domain="$(environment_domain "${environment}")"
        certbot certonly --webroot --webroot-path "${MOASHIR_ACME_ROOT}" \
            --cert-name "$(environment_cert_name "${environment}")" \
            --domain "${domain}" \
            --non-interactive --agree-tos --keep-until-expiring \
            --deploy-hook "nginx -t && systemctl reload nginx" \
            "${account_options[@]}"
        info "Certificate $(environment_cert_name "${environment}") ready for ${domain}."
    done < <(environments_in_scope "${scope}")

    info "Next: provision.sh nginx-final"
}

nginx_final() {
    local scope="$1" environment snippet
    preflight
    install_nginx_shared || true
    install -m 0644 -o root -g root "${MOASHIR_KIT}/nginx/moashir-spa-headers.conf" /etc/nginx/snippets/moashir-spa-headers.conf

    while IFS= read -r environment; do
        [[ -f "/etc/letsencrypt/live/$(environment_cert_name "${environment}")/fullchain.pem" ]] \
            || die "No certificate for ${environment}; run provision.sh certificates first."

        snippet="$(mktemp)"
        render_template "${MOASHIR_KIT}/nginx/laravel.conf.template" "${snippet}" \
            "ENV_DIR=$(environment_dir "${environment}")" \
            "FPM_SOCKET=$(environment_fpm_socket "${environment}")"
        install_if_changed "${snippet}" "/etc/nginx/snippets/moashir-${environment}-laravel.conf" 0644 root:root || true

        install_site "${environment}" "${MOASHIR_KIT}/nginx/site.conf.template"
    done < <(environments_in_scope "${scope}")

    nginx_test_and_reload
    info "Next: deploy.sh staging (from the operator workstation)"
}

status() {
    local scope="$1" environment
    preflight

    printf 'Swap: %s\n' "$(swapon --show --noheadings | awk '{print $1, $3}' | paste -sd ' ' -)"
    printf 'ClamAV daemon: %s\n' "$(systemctl is-active clamav-daemon 2>/dev/null || true)"
    printf 'Freshclam: %s\n' "$(systemctl is-active clamav-freshclam 2>/dev/null || true)"

    while IFS= read -r environment; do
        printf '\n[%s] %s\n' "${environment}" "$(environment_domain "${environment}")"
        printf '  current release: %s\n' "$(readlink "$(environment_dir "${environment}")/current" 2>/dev/null || printf 'none')"
        printf '  .env mode: %s\n' "$(stat -c '%a %U' "$(environment_env_file "${environment}")" 2>/dev/null || printf 'missing')"
        printf '  FPM socket: %s\n' "$([[ -S "$(environment_fpm_socket "${environment}")" ]] && printf present || printf missing)"
        printf '  Horizon: %s\n' "$(supervisorctl status "moashir-${environment}-horizon" 2>/dev/null | awk '{print $2}' || true)"
        printf '  certificate: %s\n' "$([[ -f "/etc/letsencrypt/live/$(environment_cert_name "${environment}")/fullchain.pem" ]] && printf present || printf missing)"
        printf '  database: %s\n' "$(database_exists "$(environment_database "${environment}")" && printf present || printf missing)"
    done < <(environments_in_scope "${scope}")
}

main() {
    local phase="${1:-}" scope="${2:-all}"

    case "${phase}" in
        system) provision_system "${scope}" ;;
        nginx-bootstrap) nginx_bootstrap "${scope}" ;;
        certificates) certificates "${scope}" ;;
        nginx-final) nginx_final "${scope}" ;;
        status) status "${scope}" ;;
        *)
            usage
            exit 2
            ;;
    esac
}

main "$@"
