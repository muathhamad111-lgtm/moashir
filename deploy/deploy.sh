#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly ROOT_DIR
readonly KIT_DIR="${ROOT_DIR}/deploy"
readonly REMOTE_KIT="/opt/moashir/deploy"
readonly ENVIRONMENT="${1:-}"
readonly ACTION="${2:-deploy}"

usage() {
    cat <<'USAGE'
Usage: DEPLOY_HOST=<server address> deploy/deploy.sh <staging|production> [action]

Actions:
  deploy                 build the SPA for the environment, upload a release, activate it, run smoke checks (default)
  rollback               point the environment back to the previous release (migrations are not reverted)
  demo-install           load the fictional demo data; passwords go to /root/moashir-<env>-demo-accounts.txt
  demo-rotate-passwords  issue new demo passwords into the same root-only file
  super-admin            create the first Super Admin interactively (the operator types the e-mail and name)
  smoke                  run the smoke checks only
  sync-kit               upload the deployment kit to /opt/moashir/deploy only

Environment:
  DEPLOY_HOST      server address (required, never stored in the repository)
  DEPLOY_SSH_USER  SSH user with root rights (default: root)
  DEPLOY_SSH_PORT  SSH port (default: 22)
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
readonly SSH_TARGET="${DEPLOY_SSH_USER:-root}@${DEPLOY_HOST}"
readonly SSH_PORT="${DEPLOY_SSH_PORT:-22}"

remote() {
    ssh -p "${SSH_PORT}" -o BatchMode=yes "${SSH_TARGET}" "$@"
}

remote_interactive() {
    ssh -t -p "${SSH_PORT}" "${SSH_TARGET}" "$@"
}

upload_directory() {
    local source="$1" destination="$2"
    COPYFILE_DISABLE=1 tar -C "${source}" --no-xattrs -czf - . \
        | remote "mkdir -p '${destination}' && tar -C '${destination}' --no-same-owner --warning=no-unknown-keyword -xzf -"
}

sync_kit() {
    local staging
    staging="$(mktemp -d)"
    cp -R "${KIT_DIR}/." "${staging}/"
    mkdir -p "${staging}/sql"
    cp "${ROOT_DIR}/backend/database/sql/production-roles.sql" "${staging}/sql/production-roles.sql"
    remote "rm -rf '${REMOTE_KIT}.next'"
    upload_directory "${staging}" "${REMOTE_KIT}.next"
    remote "chown -R root:root '${REMOTE_KIT}.next' && chmod -R go-w '${REMOTE_KIT}.next' && rm -rf '${REMOTE_KIT}.previous' && { [ ! -d '${REMOTE_KIT}' ] || mv '${REMOTE_KIT}' '${REMOTE_KIT}.previous'; } && mv '${REMOTE_KIT}.next' '${REMOTE_KIT}'"
    rm -rf "${staging}"
    info "Deployment kit uploaded to ${REMOTE_KIT}."
}

build_frontend() {
    local build="$1"
    local source="${ROOT_DIR}/muasher"

    mkdir -p "${build}/muasher"
    COPYFILE_DISABLE=1 tar -C "${source}" --exclude ./node_modules --exclude ./dist --exclude ./test-results \
        --exclude ./playwright-report --exclude './.env.local' --exclude './.env.*.local' -cf - . | tar -C "${build}/muasher" -xf -

    if [[ ! -f "${build}/muasher/.env.${ENVIRONMENT}" ]]; then
        [[ -f "${build}/muasher/.env.${ENVIRONMENT}.example" ]] || die "muasher/.env.${ENVIRONMENT}.example is missing."
        cp "${build}/muasher/.env.${ENVIRONMENT}.example" "${build}/muasher/.env.${ENVIRONMENT}"
    fi

    info "Building the SPA in ${ENVIRONMENT} mode."
    (
        cd "${build}/muasher"
        export FONTAWESOME_NPM_AUTH_TOKEN="${FONTAWESOME_NPM_AUTH_TOKEN:-}"
        npm ci --no-audit --no-fund
        npm run build -- --mode "${ENVIRONMENT}"
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

BUILD_DIR=""

remove_build_dir() {
    [[ -z ${BUILD_DIR} ]] || rm -rf "${BUILD_DIR}"
}

deploy_release() {
    local release
    release="$(date -u +%Y%m%d%H%M%S)"
    BUILD_DIR="$(mktemp -d)"
    trap remove_build_dir EXIT

    build_frontend "${BUILD_DIR}"
    prepare_backend "${BUILD_DIR}"
    sync_kit

    info "Uploading release ${release} to ${ENVIRONMENT}."
    upload_directory "${BUILD_DIR}/release" "/var/www/moashir/${ENVIRONMENT}/releases/${release}"
    remote "bash ${REMOTE_KIT}/remote/activate-release.sh ${ENVIRONMENT} ${release}"
    remote "bash ${REMOTE_KIT}/remote/smoke.sh ${ENVIRONMENT}"
}

external_checks() {
    local domain="moashir.ttshub.live"
    [[ ${ENVIRONMENT} == "staging" ]] && domain="staging.moashir.ttshub.live"

    info "External view of https://${domain}"
    curl -sS -o /dev/null -w '  SPA %{http_code}, TLS %{ssl_verify_result} (0 = valid)\n' "https://${domain}/"
    curl -sS -o /dev/null -w '  prices API %{http_code}\n' -H 'Accept: application/json' "https://${domain}/api/v1/content/prices"
}

case "${ACTION}" in
    deploy) deploy_release ;;
    rollback) sync_kit && remote "bash ${REMOTE_KIT}/remote/rollback.sh ${ENVIRONMENT}" ;;
    demo-install) remote "bash ${REMOTE_KIT}/remote/demo.sh ${ENVIRONMENT} install" ;;
    demo-rotate-passwords) remote "bash ${REMOTE_KIT}/remote/demo.sh ${ENVIRONMENT} rotate-passwords" ;;
    super-admin) remote_interactive "bash ${REMOTE_KIT}/remote/super-admin.sh ${ENVIRONMENT}" ;;
    smoke) remote "bash ${REMOTE_KIT}/remote/smoke.sh ${ENVIRONMENT}" && external_checks ;;
    sync-kit) sync_kit ;;
    *)
        usage
        exit 2
        ;;
esac
