#!/usr/bin/env bash

umask 027

readonly MOASHIR_LEGACY_USER="moashir"
readonly MOASHIR_WEB_GROUP="www-data"
readonly MOASHIR_BASE="/var/www/moashir"
readonly MOASHIR_ACME_ROOT="/var/www/moashir/acme"
readonly MOASHIR_OPT="/opt/moashir"
readonly MOASHIR_KIT="/opt/moashir/deploy"
readonly MOASHIR_ETC="/etc/moashir"
readonly MOASHIR_LOG="/var/log/moashir"
readonly MOASHIR_NGINX_LOG="/var/log/moashir/nginx"
readonly MOASHIR_LOG_ARCHIVE="/var/log/moashir/archive"
readonly MOASHIR_REDIS_ETC="/etc/moashir-redis"
readonly MOASHIR_BACKUP_SPOOL="/var/backups/moashir"
readonly MOASHIR_STATE="/var/lib/moashir-state"
readonly MOASHIR_DEPLOY_USER="moashir-deploy"
readonly MOASHIR_DEPLOY_HOME="/var/lib/moashir-deploy"
readonly MOASHIR_INCOMING="/var/lib/moashir-deploy/incoming"
readonly MOASHIR_PHP_VERSION="8.4"
readonly MOASHIR_PHP_BIN="/usr/bin/php8.4"
readonly MOASHIR_FPM_SERVICE="php8.4-fpm"
readonly MOASHIR_FPM_BIN="php-fpm8.4"
readonly MOASHIR_FPM_POOL_DIR="/etc/php/8.4/fpm/pool.d"
readonly MOASHIR_KEEP_RELEASES=3
readonly MOASHIR_DEFAULT_DB_PORT=5432
readonly MOASHIR_ENVIRONMENTS=(staging production)

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf '==> %s\n' "$*"
}

warn() {
    printf 'WARNING: %s\n' "$*" >&2
}

require_root() {
    [[ ${EUID} -eq 0 ]] || die "Run this script as root."
}

assert_environment() {
    case "${1:-}" in
        staging | production) ;;
        *) die "The environment must be staging or production." ;;
    esac
}

assert_release_id() {
    [[ ${1:-} =~ ^[0-9]{14}$ ]] || die "The release id must be a UTC timestamp (YYYYMMDDHHMMSS)."
}

environments_in_scope() {
    local scope="${1:-all}"

    if [[ ${scope} == "all" ]]; then
        printf '%s\n' "${MOASHIR_ENVIRONMENTS[@]}"
        return
    fi

    assert_environment "${scope}"
    printf '%s\n' "${scope}"
}

environment_domain() {
    case "$1" in
        staging) printf 'staging.moashir.ttshub.live' ;;
        production) printf 'moashir.ttshub.live' ;;
    esac
}

environment_dir() {
    printf '%s/%s' "${MOASHIR_BASE}" "$1"
}

environment_env_file() {
    printf '%s/shared/.env' "$(environment_dir "$1")"
}

environment_backend() {
    printf '%s/current/backend' "$(environment_dir "$1")"
}

environment_log_dir() {
    printf '%s/%s' "${MOASHIR_LOG}" "$1"
}

environment_fpm_socket() {
    printf '/run/php/php%s-fpm-moashir-%s.sock' "${MOASHIR_PHP_VERSION}" "$1"
}

environment_user() {
    printf 'moashir-%s' "$1"
}

environment_home() {
    printf '/var/lib/moashir-%s' "$1"
}

environment_runtime_user() {
    local environment="$1" dir owner
    dir="$(environment_dir "${environment}")"

    if [[ -d ${dir} ]]; then
        owner="$(stat -c '%U' "${dir}")"
        if [[ ${owner} == "${MOASHIR_LEGACY_USER}" ]]; then
            printf '%s' "${owner}"
            return
        fi
    fi

    environment_user "${environment}"
}

environment_uses_own_user() {
    [[ "$(environment_runtime_user "$1")" == "$(environment_user "$1")" ]]
}

environment_redis_socket() {
    printf '/run/moashir-redis-%s/redis.sock' "$1"
}

environment_redis_unit() {
    printf 'moashir-redis@%s.service' "$1"
}

environment_database() {
    printf 'moashir_%s' "$1"
}

environment_role() {
    printf 'moashir_%s_%s' "$1" "$2"
}

environment_owner_secrets() {
    printf '%s/%s/database-owner.env' "${MOASHIR_ETC}" "$1"
}

environment_cert_name() {
    printf 'moashir-%s' "$1"
}

environment_incoming() {
    printf '%s/%s' "${MOASHIR_INCOMING}" "$1"
}

composer_bin() {
    if [[ -x "${MOASHIR_OPT}/bin/composer" ]]; then
        printf '%s/bin/composer' "${MOASHIR_OPT}"
        return
    fi

    command -v composer || die "Composer is missing. Run provision.sh system first."
}

as_app() {
    local environment="$1"
    shift
    sudo -u "$(environment_runtime_user "${environment}")" -H -- "$@"
}

artisan() {
    local environment="$1"
    shift
    (cd "$(environment_backend "${environment}")" && as_app "${environment}" "${MOASHIR_PHP_BIN}" artisan "$@")
}

psql_admin() {
    (cd / && sudo -u postgres psql -X -q -v ON_ERROR_STOP=1 "$@")
}

environment_db_port() {
    local port
    port="$(environment_env_value "$1" DB_PORT)"
    [[ ${port} =~ ^[0-9]+$ ]] || port="${MOASHIR_DEFAULT_DB_PORT}"
    printf '%s' "${port}"
}

environment_psql_admin() {
    local environment="$1"
    shift
    psql_admin -p "$(environment_db_port "${environment}")" "$@"
}

env_get_key() {
    local file="$1" key="$2" line

    while IFS= read -r line || [[ -n ${line} ]]; do
        if [[ ${line} == "${key}="* ]]; then
            printf '%s' "${line#"${key}"=}"
            return 0
        fi
    done <"${file}"

    return 1
}

env_set_key() {
    local file="$1" key="$2" value="$3" line found=0 temporary
    temporary="$(mktemp "${file}.XXXXXX")"
    chmod 600 "${temporary}"

    while IFS= read -r line || [[ -n ${line} ]]; do
        if [[ ${line} == "${key}="* ]]; then
            printf '%s=%s\n' "${key}" "${value}" >>"${temporary}"
            found=1
        else
            printf '%s\n' "${line}" >>"${temporary}"
        fi
    done <"${file}"

    if [[ ${found} -eq 0 ]]; then
        printf '%s=%s\n' "${key}" "${value}" >>"${temporary}"
    fi

    chown --reference="${file}" "${temporary}"
    mv -f "${temporary}" "${file}"
}

environment_env_value() {
    local file
    file="$(environment_env_file "$1")"
    [[ -f ${file} ]] || return 0
    env_get_key "${file}" "$2" || true
}

environment_is_demo() {
    [[ "$(environment_env_value "$1" MOASHIR_DEMO)" == "true" ]]
}

posture_violations() {
    local environment="$1" mailer alert backup_mail
    mailer="$(environment_env_value "${environment}" MAIL_MAILER)"
    alert="$(environment_env_value "${environment}" SECURITY_ALERT_MAIL)"
    backup_mail="$(environment_env_value "${environment}" BACKUP_NOTIFICATION_MAIL)"

    case "${mailer}" in
        log | array | "") printf 'MAIL_MAILER=%s writes or drops e-mail instead of sending it (use settings)\n' "${mailer:-<empty>}" ;;
    esac
    [[ "$(environment_env_value "${environment}" APP_DEMO_DATA)" != "true" ]] || printf 'APP_DEMO_DATA=true\n'
    [[ "$(environment_env_value "${environment}" APP_DEBUG)" == "false" ]] || printf 'APP_DEBUG is not false\n'
    [[ -n ${alert} ]] || printf 'SECURITY_ALERT_MAIL is empty\n'
    [[ -n ${backup_mail} && ${backup_mail} != *example.sa ]] || printf 'BACKUP_NOTIFICATION_MAIL is empty or a placeholder\n'
}

assert_posture() {
    local environment="$1" violations

    if environment_is_demo "${environment}"; then
        info "${environment} runs in the demo posture (MOASHIR_DEMO=true): fictional data only."
        return
    fi

    violations="$(posture_violations "${environment}")"
    [[ -z ${violations} ]] || die "$(printf '%s is not in the demo posture (MOASHIR_DEMO is not true) and its .env is not ready for real operation:\n%s\nFix the .env, or opt in to the demo posture: provision.sh posture demo %s' "${environment}" "${violations}" "${environment}")"
}

release_revision() {
    local file="$1/REVISION" revision=""
    [[ -f ${file} ]] && revision="$(head -c 64 "${file}" | tr -d '[:space:]')"
    [[ ${revision} =~ ^[0-9a-f]{40}$ ]] || revision=""
    printf '%s' "${revision}"
}

release_label() {
    local release_dir="$1" revision
    revision="$(release_revision "${release_dir}")"

    if [[ -n ${revision} ]]; then
        printf '%s-%s' "$(basename "${release_dir}")" "${revision:0:12}"
        return
    fi

    basename "${release_dir}"
}

render_template() {
    local template="$1" destination="$2"
    shift 2
    local content pair key value
    shopt -u patsub_replacement 2>/dev/null || true
    content="$(<"${template}")"

    for pair in "$@"; do
        key="${pair%%=*}"
        value="${pair#*=}"
        content="${content//"{{${key}}}"/${value}}"
    done

    if [[ ${content} == *"{{"*"}}"* ]]; then
        die "Unrendered placeholder left in ${template}."
    fi

    printf '%s\n' "${content}" >"${destination}"
}

install_if_changed() {
    local source="$1" destination="$2" mode="$3" owner="$4"

    if [[ -f ${destination} ]] && cmp -s "${source}" "${destination}"; then
        rm -f "${source}"
        return 1
    fi

    install -m "${mode}" -o "${owner%%:*}" -g "${owner##*:}" "${source}" "${destination}"
    rm -f "${source}"
    return 0
}

nginx_test_and_reload() {
    nginx -t || die "nginx -t failed; nothing was reloaded. Fix the configuration above."
    systemctl reload nginx
    info "Nginx configuration tested and reloaded."
}

fpm_test_and_reload() {
    "${MOASHIR_FPM_BIN}" -t || die "${MOASHIR_FPM_BIN} -t failed; PHP-FPM was not reloaded."
    systemctl reload "${MOASHIR_FPM_SERVICE}"
    info "PHP-FPM configuration tested and reloaded."
}
