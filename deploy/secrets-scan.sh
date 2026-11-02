#!/usr/bin/env bash
set -Euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly ROOT_DIR
readonly ALLOW_FILE="${ROOT_DIR}/deploy/secrets-scan.allow"

readonly FALLBACK_EXCLUDED_PATHS=(
    ./agents ./references ./.claude ./CLAUDE.md ./DEVELOPMENT_STANDARDS.md ./FILL-FIRST.md ./GETTING-STARTED.md
    ./RUN-SCAFFOLD.md "./SCAFFOLD .md" "./منصة مؤشر.html" ./منصة-مؤشر
    ./backend/vendor ./backend/storage ./backend/.phpunit.cache ./backend/CLAUDE.md ./backend/AGENTS.md
    ./muasher/agents ./muasher/references ./muasher/.claude ./muasher/CLAUDE.md
)

readonly FALLBACK_EXCLUDED_NAMES=(
    node_modules dist dist-ssr coverage test-results "playwright-report*" .DS_Store .env "*.local" .phpunit.result.cache
)

readonly SECRET_PATTERNS=(
    '-----BEGIN [A-Z ]*PRIVATE KEY-----'
    'AKIA[0-9A-Z]{16}'
    'gh[pousr]_[A-Za-z0-9]{36,}'
    'github_pat_[A-Za-z0-9_]{20,}'
    'xox[abprs]-[A-Za-z0-9-]{10,}'
    '(sk|pk)_(live|test)_[A-Za-z0-9]{16,}'
    'sk-(ant-|proj-)?[A-Za-z0-9_-]{32,}'
    'AIza[0-9A-Za-z_-]{35}'
    '^[[:space:]]*(export[[:space:]]+)?[A-Z0-9_]*(PASSWORD|PASSWD|SECRET|TOKEN|API_KEY|APIKEY|PRIVATE_KEY|ACCESS_KEY|AUTH_KEY|DSN)[A-Z0-9_]*=[^[:space:]$]{6,}'
    'base64:[A-Za-z0-9+/]{43}='
)

readonly PLACEHOLDER_PATTERN='[=:][[:space:]]*["'\'']?(your|<|\$\{|example|change|placeholder|dummy|xxx)'

readonly IPV4_PATTERN='(^|[^0-9.])([0-9]{1,3}\.){3}[0-9]{1,3}([^0-9.]|$)'

FINDINGS=0

report() {
    printf 'FINDING: %s\n' "$*"
    FINDINGS=$((FINDINGS + 1))
}

candidate_files() {
    cd "${ROOT_DIR}" || exit 2

    if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git ls-files --cached --others --exclude-standard
        return
    fi

    local -a prune=()
    local entry
    for entry in "${FALLBACK_EXCLUDED_PATHS[@]}"; do
        prune+=(-path "${entry}" -prune -o)
    done
    for entry in "${FALLBACK_EXCLUDED_NAMES[@]}"; do
        prune+=(-name "${entry}" -prune -o)
    done
    find . "${prune[@]}" -name '.env.*' ! -name '*.example' ! -path ./backend/.env.testing -prune -o -type f -print | sed 's|^\./||'
}

is_allowed() {
    local file="$1" kind="$2" rule rule_kind rule_glob
    [[ -f ${ALLOW_FILE} ]] || return 1

    while IFS= read -r rule || [[ -n ${rule} ]]; do
        [[ -z ${rule} || ${rule} == \#* ]] && continue
        rule_kind="${rule%% *}"
        rule_glob="${rule#* }"
        [[ ${rule_kind} == "${kind}" ]] || continue
        case "${file}" in
            ${rule_glob}) return 0 ;;
        esac
    done <"${ALLOW_FILE}"

    return 1
}

check_file_name() {
    local file="$1" name
    name="$(basename "${file}")"

    case "${name}" in
        .env.example | .env.*.example | *.env.template) return ;;
        .env.testing) [[ ${file} == "backend/.env.testing" ]] && return ;;
    esac

    case "${name}" in
        .env | .env.* | *.pem | *.key | *.p12 | *.pfx | *.keystore | id_rsa* | id_ecdsa* | id_ed25519* | auth.json | *.sqlite)
            report "${file}: file type that must never be pushed"
            ;;
    esac
}

is_ipv4() {
    local octet
    local IFS=.
    for octet in $1; do
        [[ ${octet} =~ ^(0|[1-9][0-9]{0,2})$ && ${octet} -le 255 ]] || return 1
    done
}

public_ipv4_in() {
    local file="$1" address
    while IFS= read -r address; do
        case "${address}" in
            0.* | 127.* | 10.* | 192.168.* | 169.254.* | 192.0.0.* | 192.0.2.* | 198.18.* | 198.19.* | 198.51.100.* | 203.0.113.*) continue ;;
            172.1[6-9].* | 172.2[0-9].* | 172.3[01].* | 100.6[4-9].* | 100.[7-9][0-9].* | 100.1[01][0-9].* | 100.12[0-7].*) continue ;;
            22[4-9].* | 23[0-9].* | 24[0-9].* | 25[0-5].*) continue ;;
        esac
        is_ipv4 "${address}" || continue
        printf '%s\n' "${address}"
    done < <(grep -Eo '([0-9]{1,3}\.){3}[0-9]{1,3}' "${file}" | sort -u)
}

check_content() {
    local file="$1" pattern match address

    grep -Iq . "${file}" 2>/dev/null || return 0

    if ! is_allowed "${file}" secret; then
        for pattern in "${SECRET_PATTERNS[@]}"; do
            match="$(grep -EIn -e "${pattern}" "${file}" 2>/dev/null | grep -Eiv -e "${PLACEHOLDER_PATTERN}" | head -n 1 | cut -d: -f1 || true)"
            [[ -n ${match} ]] && report "${file}:${match}: looks like a secret (pattern ${pattern:0:24}...)"
        done
    fi

    if ! is_allowed "${file}" ip && grep -EIq "${IPV4_PATTERN}" "${file}" 2>/dev/null; then
        while IFS= read -r address; do
            [[ -n ${address} ]] && report "${file}: contains the public IPv4 address ${address}"
        done < <(public_ipv4_in "${file}")
    fi
}

main() {
    local file count=0
    cd "${ROOT_DIR}" || exit 2

    while IFS= read -r file; do
        [[ -f ${file} ]] || continue
        count=$((count + 1))
        check_file_name "${file}"
        check_content "${file}"
    done < <(candidate_files)

    printf 'Scanned %d files.\n' "${count}"

    if [[ ${FINDINGS} -gt 0 ]]; then
        printf '%d finding(s). Remove the secret, or add a reviewed rule to deploy/secrets-scan.allow for fictional test fixtures only.\n' "${FINDINGS}"
        exit 1
    fi

    printf 'No secrets, keys, .env files or public IP addresses found.\n'
}

main
