#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly ROOT_DIR
readonly KIT_DIR="${ROOT_DIR}/deploy"
readonly REMOTE_KIT="/opt/moashir/deploy"
readonly REMOTE_INCOMING="/var/lib/moashir-deploy/incoming"
readonly ENVIRONMENT="${1:-}"
readonly ACTION="${2:-deploy}"

usage() {
    cat <<'USAGE'
Usage: DEPLOY_HOST=<server address> deploy/deploy.sh <staging|production> [action]

Actions (root SSH unless marked CI):
  deploy                 build the SPA, upload a release, activate it, run smoke checks (default; CI)
  rollback               point the environment back to the previous release (migrations are not reverted; CI)
  smoke                  run the smoke checks only (CI)
  sync-kit               upload the deployment kit to /opt/moashir/deploy only
  demo-install           load the fictional demo data (demo posture only)
  demo-rotate-passwords  issue new demo passwords (demo posture only)
  super-admin            create the first Super Admin interactively (the operator types the e-mail and name)
  backup-run             create an encrypted backup now and send it to the off-server target
  backup-check           report the backup key, target, last backup and last restore test
  restore-test           restore the latest backup into a throw-away database and verify it

Environment:
  DEPLOY_HOST                  server address (required, never stored in the repository)
  DEPLOY_SSH_USER              root (operator) or moashir-deploy (CI deploy user); default root
  DEPLOY_SSH_PORT              SSH port (default 22)
  DEPLOY_SSH_IDENTITY_FILE     private key file to use (optional)
  DEPLOY_SSH_KNOWN_HOSTS_FILE  verified known_hosts file; enables strict host key checking (optional)
  DEPLOY_COMMIT                40-character commit id of the deployed tree (recorded in APP_RELEASE)
USAGE
}

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

info() {
    printf '==> %s\n' "$*"
}

case "${ENVIRONMENT}" in
    staging | production) ;;
    *)
        usage
        exit 2
        ;;
esac

[[ -n ${DEPLOY_HOST:-} ]] || die "Set DEPLOY_HOST to the server address for this run."
readonly SSH_USER="${DEPLOY_SSH_USER:-root}"
readonly SSH_TARGET="${SSH_USER}@${DEPLOY_HOST}"
readonly GATE_USER="moashir-deploy"
[[ ${SSH_USER} == "root" || ${SSH_USER} == "${GATE_USER}" ]] || die "DEPLOY_SSH_USER must be root (operator) or ${GATE_USER} (CI deploy user)."

SSH_OPTIONS=(-p "${DEPLOY_SSH_PORT:-22}")
if [[ -n ${DEPLOY_SSH_IDENTITY_FILE:-} ]]; then
    SSH_OPTIONS+=(-o IdentitiesOnly=yes -i "${DEPLOY_SSH_IDENTITY_FILE}")
fi
if [[ -n ${DEPLOY_SSH_KNOWN_HOSTS_FILE:-} ]]; then
    SSH_OPTIONS+=(-o StrictHostKeyChecking=yes -o UserKnownHostsFile="${DEPLOY_SSH_KNOWN_HOSTS_FILE}")
fi
readonly SSH_OPTIONS

remote() {
    ssh "${SSH_OPTIONS[@]}" -o BatchMode=yes "${SSH_TARGET}" "$@"
}

remote_interactive() {
    ssh -t "${SSH_OPTIONS[@]}" "${SSH_TARGET}" "$@"
}

uses_deploy_gate() {
    [[ ${SSH_USER} == "${GATE_USER}" ]]
}

require_operator() {
    uses_deploy_gate && die "${ACTION} needs the operator's root SSH access (DEPLOY_SSH_USER=root); the CI deploy user may only deploy, smoke and roll back."
    return 0
}

sha256_lines() {
    if command -v sha256sum >/dev/null; then
        sha256sum "$@"
    else
        shasum -a 256 "$@"
    fi
}

assemble_kit() {
    local destination="$1" digest
    cp -R "${KIT_DIR}/." "${destination}/"
    mkdir -p "${destination}/sql"
    cp "${ROOT_DIR}/backend/database/sql/production-roles.sql" "${destination}/sql/production-roles.sql"
    digest="$(cd "${destination}" && find . -type f ! -name KIT_DIGEST ! -name .DS_Store ! -name '*.md' -print | LC_ALL=C sort \
        | while IFS= read -r file; do sha256_lines "${file}"; done | sha256_lines | cut -d' ' -f1)"
    printf '%s\n' "${digest}" >"${destination}/KIT_DIGEST"
}

upload_directory() {
    local source="$1" destination="$2"
    COPYFILE_DISABLE=1 tar -C "${source}" --no-xattrs -czf - . \
        | remote "mkdir -p '${destination}' && tar -C '${destination}' --no-same-owner --warning=no-unknown-keyword -xzf -"
}

sync_kit() {
    local staging
    require_operator
    staging="$(mktemp -d)"
    assemble_kit "${staging}"
    remote "rm -rf '${REMOTE_KIT}.next'"
    upload_directory "${staging}" "${REMOTE_KIT}.next"
    remote "chown -R root:root '${REMOTE_KIT}.next' && chmod -R go-w '${REMOTE_KIT}.next' && rm -rf '${REMOTE_KIT}.previous' && { [ ! -d '${REMOTE_KIT}' ] || mv '${REMOTE_KIT}' '${REMOTE_KIT}.previous'; } && mv '${REMOTE_KIT}.next' '${REMOTE_KIT}'"
    info "Deployment kit uploaded to ${REMOTE_KIT} (digest $(cat "${staging}/KIT_DIGEST"))."
    rm -rf "${staging}"
}

verify_remote_kit() {
    local staging expected actual
    staging="$(mktemp -d)"
    assemble_kit "${staging}"
    expected="$(cat "${staging}/KIT_DIGEST")"
    rm -rf "${staging}"
    actual="$(remote kit-digest | tr -d '[:space:]')"
    [[ ${actual} == "${expected}" ]] || die "The deployment kit on the server differs from this commit (server ${actual:-unknown}, commit ${expected}). The operator must run: deploy/deploy.sh ${ENVIRONMENT} sync-kit"
    info "Deployment kit on the server matches this commit."
}

prepare_kit() {
    if uses_deploy_gate; then
        verify_remote_kit
    else
        sync_kit
    fi
}

build_frontend() {
    local build="$1" label="$2"
    local source="${ROOT_DIR}/muasher"

    mkdir -p "${build}/muasher"
    COPYFILE_DISABLE=1 tar -C "${source}" --exclude ./node_modules --exclude ./dist --exclude ./test-results \
        --exclude ./playwright-report --exclude './.env.local' --exclude './.env.*.local' -cf - . | tar -C "${build}/muasher" -xf -

    if [[ ! -f "${build}/muasher/.env.${ENVIRONMENT}" ]]; then
        [[ -f "${build}/muasher/.env.${ENVIRONMENT}.example" ]] || die "muasher/.env.${ENVIRONMENT}.example is missing."
        cp "${build}/muasher/.env.${ENVIRONMENT}.example" "${build}/muasher/.env.${ENVIRONMENT}"
    fi

    info "Building the SPA in ${ENVIRONMENT} mode (release ${label})."
    (
        cd "${build}/muasher"
        npm ci --no-audit --no-fund
        VITE_SENTRY_RELEASE="${VITE_SENTRY_RELEASE:-${label}}" npm run build -- --mode "${ENVIRONMENT}"
    )

    [[ -f "${build}/muasher/dist/index.html" ]] || die "The SPA build produced no dist/index.html."
    mkdir -p "${build}/release/frontend"
    cp -R "${build}/muasher/dist/." "${build}/release/frontend/"
}

prepare_backend() {
    local build="$1"
    mkdir -p "${build}/release/backend"
    COPYFILE_DISABLE=1 tar -C "${ROOT_DIR}/backend" \
        --exclude ./vendor --exclude ./node_modules --exclude ./storage --exclude ./tests \
        --exclude './.env' --exclude './.env.*' --exclude ./.phpunit.cache --exclude ./.phpunit.result.cache \
        --exclude './bootstrap/cache/*.php' --exclude ./docker --exclude ./compose.yaml \
        --exclude ./phpunit.xml --exclude ./phpstan.neon --exclude ./rector.php --exclude ./pint.json \
        --exclude ./.mcp.json --exclude ./boost.json --exclude ./AGENTS.md --exclude ./CLAUDE.md --exclude ./.ai \
        --exclude ./.DS_Store -cf - . | tar -C "${build}/release/backend" -xf -
}

release_commit() {
    local commit="${DEPLOY_COMMIT:-}"
    if [[ -n ${commit} && ! ${commit} =~ ^[0-9a-f]{40}$ ]]; then
        die "DEPLOY_COMMIT must be a full 40-character commit id."
    fi
    printf '%s' "${commit}"
}

release_label() {
    local release="$1" commit
    commit="$(release_commit)"
    printf '%s%s' "${release}" "${commit:+-${commit:0:12}}"
}

upload_release() {
    local release_dir="$1" release="$2"
    if uses_deploy_gate; then
        COPYFILE_DISABLE=1 tar -C "${release_dir}" --no-xattrs -czf - . | remote "upload ${ENVIRONMENT} ${release}"
    else
        upload_directory "${release_dir}" "${REMOTE_INCOMING}/${ENVIRONMENT}/${release}"
    fi
}

release_action() {
    if uses_deploy_gate; then
        remote "$*"
    else
        remote "bash ${REMOTE_KIT}/remote/release-entry.sh $*"
    fi
}

BUILD_DIR=""

remove_build_dir() {
    [[ -z ${BUILD_DIR} ]] || rm -rf "${BUILD_DIR}"
}

deploy_release() {
    local release label
    release="$(date -u +%Y%m%d%H%M%S)"
    label="$(release_label "${release}")"
    BUILD_DIR="$(mktemp -d)"
    trap remove_build_dir EXIT

    prepare_kit
    build_frontend "${BUILD_DIR}" "${label}"
    prepare_backend "${BUILD_DIR}"
    printf '%s\n' "$(release_commit)" >"${BUILD_DIR}/release/REVISION"

    info "Uploading release ${label} to ${ENVIRONMENT}."
    upload_release "${BUILD_DIR}/release" "${release}"
    release_action activate "${ENVIRONMENT}" "${release}"
    release_action smoke "${ENVIRONMENT}"
}

external_checks() {
    local domain="moashir.ttshub.live"
    [[ ${ENVIRONMENT} == "staging" ]] && domain="staging.moashir.ttshub.live"

    info "External view of https://${domain}"
    curl -sS -o /dev/null -w '  SPA %{http_code}, TLS %{ssl_verify_result} (0 = valid)\n' "https://${domain}/"
    curl -sS -o /dev/null -w '  prices API %{http_code}\n' -H 'Accept: application/json' "https://${domain}/api/v1/content/prices"
}

operator_script() {
    require_operator
    remote "bash ${REMOTE_KIT}/remote/$*"
}

case "${ACTION}" in
    deploy) deploy_release ;;
    rollback) prepare_kit && release_action rollback "${ENVIRONMENT}" ;;
    smoke) release_action smoke "${ENVIRONMENT}" && external_checks ;;
    sync-kit) sync_kit ;;
    demo-install) operator_script demo.sh "${ENVIRONMENT}" install ;;
    demo-rotate-passwords) operator_script demo.sh "${ENVIRONMENT}" rotate-passwords ;;
    super-admin) require_operator && remote_interactive "bash ${REMOTE_KIT}/remote/super-admin.sh ${ENVIRONMENT}" ;;
    backup-run) operator_script backup.sh "${ENVIRONMENT}" ;;
    backup-check) operator_script backup-check.sh "${ENVIRONMENT}" ;;
    restore-test) operator_script restore-test.sh "${ENVIRONMENT}" ;;
    *)
        usage
        exit 2
        ;;
esac
