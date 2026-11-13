#!/usr/bin/env bash

generate_secret() {
    openssl rand -hex 32
}

set_role_password() {
    local environment="$1" role="$2" password="$3" verifier
    verifier="$(printf '%s' "${password}" | "${MOASHIR_PHP_BIN}" "${MOASHIR_KIT}/bin/pg-scram-verifier.php")"
    printf 'ALTER ROLE "%s" PASSWORD %s;\n' "${role}" "'${verifier}'" | environment_psql_admin "${environment}" -d postgres
}

role_exists() {
    [[ "$(environment_psql_admin "$1" -At -d postgres -c "SELECT 1 FROM pg_roles WHERE rolname = '$2'")" == "1" ]]
}

database_exists() {
    [[ "$(environment_psql_admin "$1" -At -d postgres -c "SELECT 1 FROM pg_database WHERE datname = '$2'")" == "1" ]]
}

ensure_role() {
    local environment="$1" role="$2" connection_limit="$3"

    if ! role_exists "${environment}" "${role}"; then
        environment_psql_admin "${environment}" -d postgres -c "CREATE ROLE \"${role}\" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION"
    fi

    environment_psql_admin "${environment}" -d postgres -c "ALTER ROLE \"${role}\" CONNECTION LIMIT ${connection_limit}"
}

ensure_owner_secret() {
    local environment="$1" owner secrets password
    owner="$(environment_role "${environment}" owner)"
    secrets="$(environment_owner_secrets "${environment}")"

    if [[ -s ${secrets} ]]; then
        return
    fi

    password="$(generate_secret)"
    set_role_password "${environment}" "${owner}" "${password}"
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
    set_role_password "${environment}" "${app}" "${password}"
    env_set_key "${env_file}" DB_PASSWORD "${password}"
    info "Application role password generated and stored in ${env_file} (mode 600)."
}

ensure_database() {
    local environment="$1" database owner app maintenance
    database="$(environment_database "${environment}")"
    owner="$(environment_role "${environment}" owner)"
    app="$(environment_role "${environment}" app)"
    maintenance="$(environment_role "${environment}" maintenance)"

    ensure_role "${environment}" "${owner}" 5
    ensure_role "${environment}" "${app}" 40
    ensure_role "${environment}" "${maintenance}" 2

    if ! database_exists "${environment}" "${database}"; then
        environment_psql_admin "${environment}" -d postgres -c "CREATE DATABASE \"${database}\" OWNER \"${owner}\" ENCODING 'UTF8' TEMPLATE template0"
        info "Database ${database} created (owner ${owner})."
    fi

    environment_psql_admin "${environment}" -d postgres -c "REVOKE ALL ON DATABASE \"${database}\" FROM PUBLIC"
    environment_psql_admin "${environment}" -d postgres -c "GRANT CONNECT ON DATABASE \"${database}\" TO \"${owner}\", \"${app}\", \"${maintenance}\""

    ensure_owner_secret "${environment}"
    ensure_app_secret "${environment}"
}
