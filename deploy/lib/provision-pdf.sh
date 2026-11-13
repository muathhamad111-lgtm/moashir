#!/usr/bin/env bash

readonly CHROME_HEADLESS_VERSION="156.0.8078.80"
readonly CHROME_HEADLESS_SHA256="547dcd374e02961bf998f6046a267644f8502225c36bb78ca307dab32a0b3eb4"
readonly CHROME_ROOT="${MOASHIR_OPT}/chrome-headless-shell"
readonly CHROME_BINARY_PATH="chrome-headless-shell-linux64/chrome-headless-shell"
readonly CHROME_DOWNLOAD_BASE="https://storage.googleapis.com/chrome-for-testing-public"
readonly CHROME_APPARMOR_PROFILE="moashir-chrome-headless-shell"
readonly CHROME_KEEP_VERSIONS=2
readonly CHROME_PACKAGES=(
    unzip poppler-utils fontconfig ca-certificates libnss3 libnspr4 libatk1.0-0t64 libatk-bridge2.0-0t64 libatspi2.0-0t64
    libcups2t64 libdrm2 libdbus-1-3 libexpat1 libxkbcommon0 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxrandr2
    libx11-6 libxcb1 libgbm1 libasound2t64 libpango-1.0-0 libcairo2 libglib2.0-0t64 libudev1
)

chrome_version_dir() {
    printf '%s/%s' "${CHROME_ROOT}" "$1"
}

ensure_chrome_headless_shell() {
    local target binary work actual
    target="$(chrome_version_dir "${CHROME_HEADLESS_VERSION}")"
    binary="${target}/${CHROME_BINARY_PATH}"

    if [[ -x ${binary} ]]; then
        info "chrome-headless-shell ${CHROME_HEADLESS_VERSION} already installed."
        return
    fi

    work="$(mktemp -d)"
    curl -fsSL --proto '=https' --tlsv1.2 \
        "${CHROME_DOWNLOAD_BASE}/${CHROME_HEADLESS_VERSION}/linux64/chrome-headless-shell-linux64.zip" -o "${work}/shell.zip"
    actual="$(sha256sum "${work}/shell.zip" | cut -d' ' -f1)"
    if [[ ${actual} != "${CHROME_HEADLESS_SHA256}" ]]; then
        rm -rf "${work}"
        die "chrome-headless-shell ${CHROME_HEADLESS_VERSION} checksum mismatch; nothing was installed."
    fi

    install -d -m 0755 -o root -g root "${CHROME_ROOT}"
    rm -rf "${target}.partial"
    install -d -m 0755 -o root -g root "${target}.partial"
    unzip -q "${work}/shell.zip" -d "${target}.partial"
    chown -R root:root "${target}.partial"
    chmod -R u=rwX,go=rX "${target}.partial"
    mv "${target}.partial" "${target}"
    rm -rf "${work}"
    info "chrome-headless-shell ${CHROME_HEADLESS_VERSION} installed in ${target} (checksum verified, root-owned)."
}

ensure_chrome_libraries() {
    local binary missing
    binary="$(chrome_version_dir "${CHROME_HEADLESS_VERSION}")/${CHROME_BINARY_PATH}"
    missing="$(ldd "${binary}" | awk '/not found/ {print $1}' | sort -u | paste -sd ' ' -)"

    [[ -z ${missing} ]] || die "chrome-headless-shell is missing libraries: ${missing}. Install the packages that provide them (apt-file search <library>) and run provision.sh pdf-renderer again."
    info "chrome-headless-shell finds every shared library."
}

ensure_chrome_apparmor() {
    local copy
    if ! command -v apparmor_parser >/dev/null || [[ ! -d /sys/kernel/security/apparmor ]]; then
        warn "AppArmor is not active; no profile installed. The Chrome sandbox needs unprivileged user namespaces."
        return
    fi

    copy="$(mktemp)"
    cp "${MOASHIR_KIT}/apparmor/${CHROME_APPARMOR_PROFILE}" "${copy}"
    if install_if_changed "${copy}" "/etc/apparmor.d/${CHROME_APPARMOR_PROFILE}" 0644 root:root; then
        info "AppArmor profile ${CHROME_APPARMOR_PROFILE} written."
    fi
    apparmor_parser -r -W "/etc/apparmor.d/${CHROME_APPARMOR_PROFILE}"
    info "AppArmor profile ${CHROME_APPARMOR_PROFILE} loaded (user namespaces for the headless shell only; the Chrome sandbox stays on)."
}

activate_chrome_version() {
    local link="${CHROME_ROOT}/current" next="${CHROME_ROOT}/current.next"
    ln -sfn "$(chrome_version_dir "${CHROME_HEADLESS_VERSION}")" "${next}"
    mv -Tf "${next}" "${link}"
    info "${link} -> ${CHROME_HEADLESS_VERSION}"
}

check_chrome_runs() {
    local user="$1" version
    version="$(sudo -u "${user}" -H -- "${CHROME_ROOT}/current/${CHROME_BINARY_PATH}" --version 2>&1 || true)"
    [[ ${version} == *"${CHROME_HEADLESS_VERSION}"* ]] || die "chrome-headless-shell does not start as ${user}: ${version}"
    info "${version} starts as ${user}."
}

prune_chrome_versions() {
    local version kept=0 current
    current="$(basename "$(readlink -f "${CHROME_ROOT}/current")")"

    while IFS= read -r version; do
        [[ -z ${version} || ${version} == "${current}" ]] && continue
        kept=$((kept + 1))
        if [[ ${kept} -ge ${CHROME_KEEP_VERSIONS} ]]; then
            rm -rf "$(chrome_version_dir "${version}")"
            info "Removed chrome-headless-shell ${version}."
        fi
    done < <(find "${CHROME_ROOT}" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %f\n' | sort -rn | cut -d' ' -f2)
}

ensure_pdf_environment() {
    local environment="$1" env_file
    env_file="$(environment_env_file "${environment}")"
    [[ -f ${env_file} ]] || return 0

    if [[ -z "$(env_get_key "${env_file}" PDF_RENDERER || true)" ]]; then
        env_set_key "${env_file}" PDF_RENDERER chromium
        info "PDF_RENDERER=chromium set in ${env_file}."
    fi

    if [[ -z "$(env_get_key "${env_file}" PDF_CHROME_BINARY || true)" ]]; then
        env_set_key "${env_file}" PDF_CHROME_BINARY "${CHROME_ROOT}/current/${CHROME_BINARY_PATH}"
        info "PDF_CHROME_BINARY set in ${env_file}."
    fi

    [[ -n "$(env_get_key "${env_file}" PDF_RENDER_TIMEOUT_SECONDS || true)" ]] || env_set_key "${env_file}" PDF_RENDER_TIMEOUT_SECONDS 60
}

provision_pdf_renderer() {
    local scope="$1" environment

    ensure_chrome_headless_shell
    ensure_chrome_libraries
    ensure_chrome_apparmor
    activate_chrome_version
    prune_chrome_versions

    while IFS= read -r environment; do
        if id "$(environment_runtime_user "${environment}")" >/dev/null 2>&1; then
            check_chrome_runs "$(environment_runtime_user "${environment}")"
        fi
        ensure_pdf_environment "${environment}"
    done < <(environments_in_scope "${scope}")

    info "PDF renderer ready. Rebuild the configuration cache with the next deployment (deploy.sh <env>) and run its smoke checks."
}
