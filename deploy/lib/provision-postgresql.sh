#!/usr/bin/env bash

readonly POSTGRESQL_MAJOR=17
readonly PGDG_KEY_URL="https://www.postgresql.org/media/keys/ACCC4CF8.asc"
readonly PGDG_KEY_FINGERPRINT="B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8"
readonly PGDG_KEYRING="/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc"
readonly PGDG_SOURCES="/etc/apt/sources.list.d/pgdg.sources"

existing_postgresql_clusters() {
    if command -v pg_lsclusters >/dev/null; then
        pg_lsclusters --no-header 2>/dev/null | awk '{ printf "%s/%s (port %s) ", $1, $2, $3 }'
    fi
}

install_pgdg_repository() {
    local work fingerprint
    work="$(mktemp -d)"
    install -d -m 0700 "${work}/gnupg"
    curl -fsSL --proto '=https' --tlsv1.2 "${PGDG_KEY_URL}" -o "${work}/pgdg.asc"
    fingerprint="$(gpg --homedir "${work}/gnupg" --batch --show-keys --with-colons "${work}/pgdg.asc" 2>/dev/null | awk -F: '$1 == "fpr" { print $10; exit }')"
    if [[ ${fingerprint} != "${PGDG_KEY_FINGERPRINT}" ]]; then
        rm -rf "${work}"
        die "The PostgreSQL repository key fingerprint does not match ${PGDG_KEY_FINGERPRINT}; nothing was installed."
    fi

    install -d -m 0755 "$(dirname "${PGDG_KEYRING}")"
    install -m 0644 -o root -g root "${work}/pgdg.asc" "${PGDG_KEYRING}"
    rm -rf "${work}"
    printf 'Types: deb\nURIs: https://apt.postgresql.org/pub/repos/apt\nSuites: noble-pgdg\nComponents: main\nSigned-By: %s\n' "${PGDG_KEYRING}" >"${PGDG_SOURCES}"
    chmod 0644 "${PGDG_SOURCES}"
    info "PostgreSQL apt repository added (key ${PGDG_KEY_FINGERPRINT} verified)."
}

provision_postgresql() {
    local clusters
    require_root
    clusters="$(existing_postgresql_clusters)"

    if [[ -n ${clusters} ]]; then
        info "PostgreSQL is already installed here: ${clusters}. Nothing was changed; an existing server keeps its cluster (RUNBOOK: PostgreSQL 17)."
        return
    fi

    install_pgdg_repository
    DEBIAN_FRONTEND=noninteractive apt-get update -q
    DEBIAN_FRONTEND=noninteractive apt-get install -y -q --no-install-recommends "postgresql-${POSTGRESQL_MAJOR}"
    info "PostgreSQL ${POSTGRESQL_MAJOR} installed: $(existing_postgresql_clusters)"
}

warn_if_old_postgresql() {
    local environment="$1" version
    version="$(environment_psql_admin "${environment}" -At -d postgres -c 'SHOW server_version_num')"
    if [[ ${version} =~ ^[0-9]+$ && ${version} -lt $((POSTGRESQL_MAJOR * 10000)) ]]; then
        warn "${environment} uses PostgreSQL ${version:0:2}; the approved version is ${POSTGRESQL_MAJOR}+ (RUNBOOK: PostgreSQL 17)."
    fi
}
