#!/usr/bin/env bash

readonly STATIC_BODY_LIMIT="64k"
readonly API_BODY_LIMIT="2m"
readonly PUBLIC_IMAGE_BODY_LIMIT="5376k"
readonly ENTITY_LOGO_BODY_LIMIT="2304k"
readonly BRANDING_LOGO_BODY_LIMIT="1280k"

install_nginx_shared() {
    local copy
    copy="$(mktemp)"
    cp "${MOASHIR_KIT}/nginx/moashir-http.conf" "${copy}"
    install_if_changed "${copy}" /etc/nginx/conf.d/moashir-http.conf 0644 root:root || true
}

install_site() {
    local environment="$1" template="$2" rendered available enabled
    rendered="$(mktemp)"
    available="/etc/nginx/sites-available/moashir-${environment}.conf"
    enabled="/etc/nginx/sites-enabled/moashir-${environment}.conf"

    render_template "${template}" "${rendered}" \
        "DOMAIN=$(environment_domain "${environment}")" \
        "ENVIRONMENT=${environment}" \
        "ACME_ROOT=${MOASHIR_ACME_ROOT}" \
        "ENV_DIR=$(environment_dir "${environment}")" \
        "CERT_NAME=$(environment_cert_name "${environment}")" \
        "NGINX_LOG_DIR=${MOASHIR_NGINX_LOG}" \
        "STATIC_BODY_LIMIT=${STATIC_BODY_LIMIT}" \
        "API_BODY_LIMIT=${API_BODY_LIMIT}" \
        "EVIDENCE_BODY_LIMIT=$((EVIDENCE_UPLOAD_CEILING_MB + 1))m" \
        "PUBLIC_IMAGE_BODY_LIMIT=${PUBLIC_IMAGE_BODY_LIMIT}" \
        "ENTITY_LOGO_BODY_LIMIT=${ENTITY_LOGO_BODY_LIMIT}" \
        "BRANDING_LOGO_BODY_LIMIT=${BRANDING_LOGO_BODY_LIMIT}"

    install_if_changed "${rendered}" "${available}" 0644 root:root || true
    ln -sfn "${available}" "${enabled}"
}

nginx_bootstrap() {
    local scope="$1" environment
    preflight
    grep -Rqs "sites-enabled" /etc/nginx/nginx.conf || die "/etc/nginx/nginx.conf does not include sites-enabled."
    ensure_logrotate
    install_nginx_shared

    while IFS= read -r environment; do
        if [[ -f "/etc/letsencrypt/live/$(environment_cert_name "${environment}")/fullchain.pem" ]]; then
            info "Certificate for ${environment} already exists; keeping the final server block."
            continue
        fi
        install_site "${environment}" "${MOASHIR_KIT}/nginx/site-bootstrap.conf.template"
    done < <(environments_in_scope "${scope}")

    nginx_test_and_reload
    info "Next: provision.sh certificates"
}

certificates() {
    local scope="$1" environment domain
    local -a account_options=()
    preflight

    if [[ -n ${MOASHIR_CERTBOT_EMAIL:-} ]]; then
        account_options+=(--email "${MOASHIR_CERTBOT_EMAIL}")
    else
        account_options+=(--register-unsafely-without-email)
    fi

    if [[ -n ${MOASHIR_CERTBOT_ACCOUNT:-} ]]; then
        account_options+=(--account "${MOASHIR_CERTBOT_ACCOUNT}")
    fi

    while IFS= read -r environment; do
        domain="$(environment_domain "${environment}")"
        certbot certonly --webroot --webroot-path "${MOASHIR_ACME_ROOT}" \
            --cert-name "$(environment_cert_name "${environment}")" \
            --domain "${domain}" \
            --non-interactive --agree-tos --keep-until-expiring \
            --deploy-hook "nginx -t && systemctl reload nginx" \
            "${account_options[@]}"
        info "Certificate $(environment_cert_name "${environment}") ready for ${domain}."
    done < <(environments_in_scope "${scope}")

    info "Next: provision.sh nginx-final"
}

nginx_final() {
    local scope="$1" environment snippet
    preflight
    ensure_logrotate
    install_nginx_shared
    install -m 0644 -o root -g root "${MOASHIR_KIT}/nginx/moashir-spa-headers.conf" /etc/nginx/snippets/moashir-spa-headers.conf

    while IFS= read -r environment; do
        [[ -f "/etc/letsencrypt/live/$(environment_cert_name "${environment}")/fullchain.pem" ]] \
            || die "No certificate for ${environment}; run provision.sh certificates first."

        snippet="$(mktemp)"
        render_template "${MOASHIR_KIT}/nginx/laravel.conf.template" "${snippet}" \
            "ENV_DIR=$(environment_dir "${environment}")" \
            "FPM_SOCKET=$(environment_fpm_socket "${environment}")"
        install_if_changed "${snippet}" "/etc/nginx/snippets/moashir-${environment}-laravel.conf" 0644 root:root || true

        install_site "${environment}" "${MOASHIR_KIT}/nginx/site.conf.template"
    done < <(environments_in_scope "${scope}")

    nginx_test_and_reload
    archive_legacy_nginx_logs
    info "Next: deploy.sh staging (from the operator workstation)"
}

nginx_default_tls() {
    local others
    preflight
    others="$(find /etc/nginx/sites-enabled /etc/nginx/conf.d -maxdepth 1 \( -type f -o -type l \) ! -name 'moashir-*' -print 2>/dev/null | paste -sd ' ' -)"
    [[ -z ${others} ]] || die "Other Nginx sites are enabled (${others}); a default TLS server would change how they answer. Nothing installed (RUNBOOK: TLS)."

    install -m 0644 -o root -g root "${MOASHIR_KIT}/nginx/default-server-tls.conf" /etc/nginx/conf.d/moashir-default-tls.conf
    nginx_test_and_reload
    info "Default TLS server installed: handshakes for unknown names are rejected and the Moashir TLS policy applies to the whole :443."
}
