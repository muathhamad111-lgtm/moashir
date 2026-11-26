#!/usr/bin/env bash

readonly DEPLOY_SUDOERS="/etc/sudoers.d/moashir-deploy"

deploy_key_is_strong() {
    local summary bits type
    summary="$(ssh-keygen -l -f "$1" 2>/dev/null)" || return 1
    bits="$(awk '{ print $1 }' <<<"${summary}")"
    type="$(awk '{ print $NF }' <<<"${summary}")"

    case "${type}" in
        "(ED25519)") return 0 ;;
        "(ECDSA)") [[ ${bits} -ge 256 ]] ;;
        "(RSA)") [[ ${bits} -ge 3072 ]] ;;
        *) return 1 ;;
    esac
}

ensure_incoming() {
    local environment="$1" owner="root"
    if id "${MOASHIR_DEPLOY_USER}" >/dev/null 2>&1; then
        owner="${MOASHIR_DEPLOY_USER}"
    fi

    install -d -m 0711 -o root -g root "${MOASHIR_DEPLOY_HOME}" "${MOASHIR_INCOMING}"
    install -d -m 0700 -o "${owner}" -g "${owner}" "$(environment_incoming "${environment}")"
}

install_deploy_sudoers() {
    local copy
    copy="$(mktemp)"
    cp "${MOASHIR_KIT}/sudoers/moashir-deploy" "${copy}"
    visudo -c -q -f "${copy}" || die "The sudoers rule did not pass visudo; nothing was installed."
    install -m 0440 -o root -g root "${copy}" "${DEPLOY_SUDOERS}"
    rm -f "${copy}"
}

install_deploy_key() {
    local key_file="$1" entry
    entry="$(printf 'command="/usr/bin/bash %s/remote/deploy-gate.sh",restrict %s' "${MOASHIR_KIT}" "$(head -n 1 "${key_file}")")"
    install -d -m 0750 -o root -g "${MOASHIR_DEPLOY_USER}" "${MOASHIR_DEPLOY_HOME}/.ssh"
    printf '%s\n' "${entry}" >"${MOASHIR_DEPLOY_HOME}/.ssh/authorized_keys.next"
    install -m 0640 -o root -g "${MOASHIR_DEPLOY_USER}" "${MOASHIR_DEPLOY_HOME}/.ssh/authorized_keys.next" "${MOASHIR_DEPLOY_HOME}/.ssh/authorized_keys"
    rm -f "${MOASHIR_DEPLOY_HOME}/.ssh/authorized_keys.next"
}

provision_deploy_user() {
    local key_file="${MOASHIR_DEPLOY_PUBLIC_KEY_FILE:-}" environment
    require_root
    [[ -f ${key_file} ]] || die "Set MOASHIR_DEPLOY_PUBLIC_KEY_FILE to a file holding the deploy PUBLIC key (one line)."
    [[ "$(grep -c . "${key_file}")" -eq 1 ]] || die "${key_file} must hold exactly one public key line."
    deploy_key_is_strong "${key_file}" || die "Use an ECDSA P-256/P-384, Ed25519 or RSA 3072+ public key."

    if ! id "${MOASHIR_DEPLOY_USER}" >/dev/null 2>&1; then
        useradd --system --user-group --home-dir "${MOASHIR_DEPLOY_HOME}" --no-create-home --shell /bin/bash "${MOASHIR_DEPLOY_USER}"
        usermod -p '*' "${MOASHIR_DEPLOY_USER}"
        info "Deploy user ${MOASHIR_DEPLOY_USER} created (no password, key login through the deploy gate only)."
    fi

    for environment in "${MOASHIR_ENVIRONMENTS[@]}"; do
        ensure_incoming "${environment}"
    done
    install_deploy_key "${key_file}"
    install_deploy_sudoers
    info "Deploy user ready: ${MOASHIR_DEPLOY_USER} may upload releases and run activate / smoke / rollback through ${MOASHIR_KIT}/remote/release-entry.sh only."
}
