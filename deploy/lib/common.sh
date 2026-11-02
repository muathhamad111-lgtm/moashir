#!/usr/bin/env bash

umask 027

readonly MOASHIR_APP_USER="moashir"
readonly MOASHIR_WEB_GROUP="www-data"
readonly MOASHIR_BASE="/var/www/moashir"
readonly MOASHIR_ACME_ROOT="/var/www/moashir/acme"
readonly MOASHIR_HOME="/var/lib/moashir"
readonly MOASHIR_OPT="/opt/moashir"
readonly MOASHIR_KIT="/opt/moashir/deploy"
readonly MOASHIR_ETC="/etc/moashir"
readonly MOASHIR_LOG="/var/log/moashir"
readonly MOASHIR_PHP_VERSION="8.4"
readonly MOASHIR_PHP_BIN="/usr/bin/php8.4"
readonly MOASHIR_FPM_SERVICE="php8.4-fpm"
readonly MOASHIR_FPM_BIN="php-fpm8.4"
readonly MOASHIR_FPM_POOL_DIR="/etc/php/8.4/fpm/pool.d"
readonly MOASHIR_KEEP_RELEASES=3
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

composer_bin() {
    if [[ -x "${MOASHIR_OPT}/bin/composer" ]]; then
        printf '%s/bin/composer' "${MOASHIR_OPT}"
        return
    fi

    command -v composer || die "Composer is missing. Run provision.sh system first."
}

as_app() {
    sudo -u "${MOASHIR_APP_USER}" -H -- "$@"
}

artisan() {
    local environment="$1"
    shift
    (cd "$(environment_backend "${environment}")" && as_app "${MOASHIR_PHP_BIN}" artisan "$@")
}

psql_admin() {
    (cd / && sudo -u postgres psql -X -q -v ON_ERROR_STOP=1 "$@")
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
