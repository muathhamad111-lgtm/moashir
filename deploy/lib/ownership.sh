#!/usr/bin/env bash

readonly MOASHIR_ENV_FILE_MODE="0640"
readonly RELEASE_CACHE_PATH="backend/bootstrap/cache"
readonly RELEASE_VENDOR_PATH="backend/vendor"
readonly RELEASE_FRONTEND_PATH="frontend"
readonly RELEASE_TRAVERSED_PATHS=("backend" "backend/public")

secure_env_file() {
    local environment="$1" file
    file="$(environment_env_file "${environment}")"
    [[ -f ${file} ]] || return 0
    chmod "${MOASHIR_ENV_FILE_MODE}" "${file}"
    chown "root:$(environment_user "${environment}")" "${file}"
}

secure_environment_tree() {
    local environment="$1" dir
    dir="$(environment_dir "${environment}")"
    install -d -m 0711 -o root -g root "${dir}" "${dir}/releases" "${dir}/shared"
    secure_env_file "${environment}"
}

grant_release_cache() {
    local cache="$1/${RELEASE_CACHE_PATH}" user="$2"
    install -d -m 0750 -o "${user}" -g "${user}" "${cache}"
    chown -R "${user}:${user}" "${cache}"
    find "${cache}" -type d -exec chmod 0750 {} +
    find "${cache}" -type f -exec chmod 0640 {} +
}

grant_release_vendor() {
    install -d -m 0750 -o "$2" -g "$2" "$1/${RELEASE_VENDOR_PATH}"
}

find_in_backend() {
    local release="$1"
    shift
    find "${release}" \( -path "${release}/${RELEASE_CACHE_PATH}" -o -path "${release}/${RELEASE_FRONTEND_PATH}" \) -prune -o "$@"
}

seal_backend() {
    local release="$1" user="$2" path
    local -a traversed=("${release}") not_traversed=()
    for path in "${RELEASE_TRAVERSED_PATHS[@]}"; do
        traversed+=("${release}/${path}")
        not_traversed+=(! -path "${release}/${path}")
    done
    not_traversed+=(! -path "${release}")

    find_in_backend "${release}" -exec chown -h "root:${user}" {} +
    find_in_backend "${release}" -type f -exec chmod 0640 {} +
    find_in_backend "${release}" -type d "${not_traversed[@]}" -exec chmod 0750 {} +
    for path in "${traversed[@]}"; do
        if [[ -d ${path} ]]; then
            chmod 0751 "${path}"
        fi
    done
}

seal_frontend() {
    local frontend="$1/${RELEASE_FRONTEND_PATH}"
    [[ -d ${frontend} ]] || return 0
    chown -R "root:${MOASHIR_WEB_GROUP}" "${frontend}"
    find "${frontend}" -type d -exec chmod 0750 {} +
    find "${frontend}" -type f -exec chmod 0640 {} +
}

seal_release() {
    local release="$1" user="$2"
    seal_backend "${release}" "${user}"
    seal_frontend "${release}"
    grant_release_cache "${release}" "${user}"
}

seal_all_releases() {
    local environment="$1" user="$2" release
    while IFS= read -r release; do
        [[ -d ${release}/backend ]] || continue
        seal_release "${release}" "${user}"
    done < <(find "$(environment_dir "${environment}")/releases" -mindepth 1 -maxdepth 1 -type d -print)
}
