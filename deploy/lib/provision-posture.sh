#!/usr/bin/env bash

demo_record_count() {
    local environment="$1" database exists
    database="$(environment_database "${environment}")"
    exists="$(environment_psql_admin "${environment}" -At -d "${database}" -c "SELECT to_regclass('public.demo_records') IS NOT NULL")"
    if [[ ${exists} != "t" ]]; then
        printf '0'
        return
    fi
    environment_psql_admin "${environment}" -At -d "${database}" -c "SELECT count(*) FROM public.demo_records"
}

set_demo_posture() {
    local environment="$1"
    env_set_key "$(environment_env_file "${environment}")" MOASHIR_DEMO true
    info "${environment}: demo posture (MOASHIR_DEMO=true). Fictional data only; demo-install and the log mailer are allowed."
}

set_real_posture() {
    local environment="$1" env_file mailer demo_rows violations
    env_file="$(environment_env_file "${environment}")"
    demo_rows="$(demo_record_count "${environment}")"
    [[ ${demo_rows} == "0" ]] \
        || die "${environment} holds ${demo_rows} demo record(s). Real operation needs a fresh database without demo data (SEC-06); see RUNBOOK: Environment postures."

    env_set_key "${env_file}" MOASHIR_DEMO false
    env_set_key "${env_file}" APP_DEMO_DATA false
    mailer="$(env_get_key "${env_file}" MAIL_MAILER || true)"
    case "${mailer}" in
        log | array | "") env_set_key "${env_file}" MAIL_MAILER settings ;;
    esac

    if [[ -f "/root/moashir-${environment}-demo-accounts.txt" ]]; then
        shred -u "/root/moashir-${environment}-demo-accounts.txt"
        info "The demo accounts file of ${environment} was shredded."
    fi

    violations="$(posture_violations "${environment}")"
    if [[ -n ${violations} ]]; then
        warn "$(printf 'Before the next deployment of %s, fix in %s:\n%s' "${environment}" "${env_file}" "${violations}")"
    fi
    info "${environment}: real-operation posture (MOASHIR_DEMO=false, APP_DEMO_DATA=false, MAIL_MAILER=settings)."
}

set_posture() {
    local mode="$1" scope="$2" environment
    while IFS= read -r environment; do
        [[ -f "$(environment_env_file "${environment}")" ]] || die "$(environment_env_file "${environment}") does not exist; run provision.sh system first."
        case "${mode}" in
            demo) set_demo_posture "${environment}" ;;
            real) set_real_posture "${environment}" ;;
            *) die "The posture must be demo or real." ;;
        esac
    done < <(environments_in_scope "${scope}")
    info "Rebuild the configuration cache with the next deployment (deploy.sh <env>)."
}
