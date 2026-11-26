#!/usr/bin/env bash

readonly BACKUP_CURL_OPTIONS=(--fail --silent --show-error --proto '=https' --tlsv1.2 --connect-timeout 20 --retry 3 --retry-delay 5)
readonly BACKUP_SAFE_PATH='^[A-Za-z0-9._/-]*$'
readonly BACKUP_SAFE_NAME='^[A-Za-z0-9._-]+$'

target_call() {
    local operation="$1" environment="$2" driver
    shift 2
    driver="$(backup_target_driver "${environment}")"

    case "${driver}" in
        s3 | sftp) "${driver}_${operation}" "${environment}" "$@" ;;
        *) die "No off-server backup target is configured for ${environment} ($(backup_target_file "${environment}"))." ;;
    esac
}

target_require() {
    target_call require "$1"
}

target_put() {
    target_call put "$@"
}

target_list_sets() {
    target_call list_sets "$1"
}

target_list_files() {
    target_call list_files "$@"
}

target_get() {
    target_call get "$@"
}

target_delete_set() {
    target_call delete_set "$@"
}

target_set_is_complete() {
    local files
    files="$(target_list_files "$1" "$2")"
    grep -qx "${BACKUP_CHECKSUMS}" <<<"${files}"
}

target_location() {
    case "$(backup_target_driver "$1")" in
        s3) printf 's3 %s/%s' "$(backup_setting "$1" BACKUP_S3_ENDPOINT)" "$(backup_setting "$1" BACKUP_S3_BUCKET)" ;;
        sftp) printf 'sftp %s:%s' "$(backup_setting "$1" BACKUP_SFTP_HOST)" "$(backup_setting "$1" BACKUP_SFTP_PATH)" ;;
        *) printf 'none' ;;
    esac
}

require_settings() {
    local environment="$1" key
    shift
    for key in "$@"; do
        [[ -n "$(backup_setting "${environment}" "${key}")" ]] || die "${key} is empty in $(backup_target_file "${environment}")."
    done
}

s3_require() {
    local environment="$1"
    require_settings "${environment}" BACKUP_S3_ENDPOINT BACKUP_S3_REGION BACKUP_S3_BUCKET BACKUP_S3_ACCESS_KEY_ID BACKUP_S3_SECRET_ACCESS_KEY
    [[ "$(backup_setting "${environment}" BACKUP_S3_ENDPOINT)" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?/?$ ]] || die "BACKUP_S3_ENDPOINT must be an https:// host without a path."
    [[ "$(backup_setting "${environment}" BACKUP_S3_BUCKET)" =~ ^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$ ]] || die "BACKUP_S3_BUCKET is not a valid bucket name."
    [[ "$(backup_setting "${environment}" BACKUP_S3_PREFIX)" =~ ${BACKUP_SAFE_PATH} ]] || die "BACKUP_S3_PREFIX may only hold letters, digits, dot, dash, underscore and slash."
}

s3_prefix() {
    local prefix
    prefix="$(backup_setting "$1" BACKUP_S3_PREFIX)"
    prefix="${prefix#/}"
    prefix="${prefix%/}"
    printf '%s%s' "${prefix:+${prefix}/}" "$1"
}

s3_bucket_url() {
    local endpoint
    endpoint="$(backup_setting "$1" BACKUP_S3_ENDPOINT)"
    printf '%s/%s' "${endpoint%/}" "$(backup_setting "$1" BACKUP_S3_BUCKET)"
}

s3_credentials_config() {
    local id secret
    id="$(backup_setting "$1" BACKUP_S3_ACCESS_KEY_ID)"
    secret="$(backup_setting "$1" BACKUP_S3_SECRET_ACCESS_KEY)"
    id="${id//\\/\\\\}"
    secret="${secret//\\/\\\\}"
    printf 'user = "%s:%s"\n' "${id//\"/\\\"}" "${secret//\"/\\\"}"
}

s3_curl() {
    local environment="$1"
    shift
    s3_credentials_config "${environment}" \
        | curl "${BACKUP_CURL_OPTIONS[@]}" --config - --aws-sigv4 "aws:amz:$(backup_setting "${environment}" BACKUP_S3_REGION):s3" "$@"
}

s3_list() {
    local environment="$1" prefix="$2" query response
    query="list-type=2&prefix=${prefix//\//%2F}"
    [[ ${3:-} == "delimited" ]] && query+="&delimiter=%2F"
    response="$(s3_curl "${environment}" "$(s3_bucket_url "${environment}")?${query}")"
    [[ ${response} != *"<IsTruncated>true</IsTruncated>"* ]] || warn "The object listing of ${prefix} is truncated at 1000 entries."
    printf '%s' "${response}"
}

s3_put() {
    local environment="$1" set="$2" file="$3" key
    key="$(s3_prefix "${environment}")/${set}/$(basename "${file}")"
    s3_curl "${environment}" -o /dev/null -H "x-amz-content-sha256: $(sha256sum "${file}" | cut -d' ' -f1)" \
        -T "${file}" "$(s3_bucket_url "${environment}")/${key}"
}

s3_list_sets() {
    local environment="$1" listing name
    listing="$(s3_list "${environment}" "$(s3_prefix "${environment}")/" delimited)"
    while IFS= read -r name; do
        name="${name%/}"
        name="${name##*/}"
        if backup_is_set_name "${environment}" "${name}"; then
            printf '%s\n' "${name}"
        fi
    done < <(grep -o '<Prefix>[^<]*</Prefix>' <<<"${listing}" | sed -e 's|<Prefix>||' -e 's|</Prefix>||' || true)
}

s3_list_files() {
    local environment="$1" set="$2" listing name
    listing="$(s3_list "${environment}" "$(s3_prefix "${environment}")/${set}/")"
    while IFS= read -r name; do
        name="${name##*/}"
        if [[ ${name} =~ ${BACKUP_SAFE_NAME} ]]; then
            printf '%s\n' "${name}"
        fi
    done < <(grep -o '<Key>[^<]*</Key>' <<<"${listing}" | sed -e 's|<Key>||' -e 's|</Key>||' || true)
}

s3_get() {
    local environment="$1" set="$2" name="$3" destination="$4"
    s3_curl "${environment}" -o "${destination}" "$(s3_bucket_url "${environment}")/$(s3_prefix "${environment}")/${set}/${name}"
}

s3_delete_set() {
    local environment="$1" set="$2" files name
    files="$(s3_list_files "${environment}" "${set}")"
    while IFS= read -r name; do
        [[ -n ${name} ]] || continue
        s3_curl "${environment}" -o /dev/null -X DELETE "$(s3_bucket_url "${environment}")/$(s3_prefix "${environment}")/${set}/${name}"
    done <<<"${files}"
}

sftp_require() {
    local environment="$1" identity known_hosts
    require_settings "${environment}" BACKUP_SFTP_HOST BACKUP_SFTP_USER BACKUP_SFTP_PATH BACKUP_SFTP_IDENTITY_FILE BACKUP_SFTP_KNOWN_HOSTS_FILE
    identity="$(backup_setting "${environment}" BACKUP_SFTP_IDENTITY_FILE)"
    known_hosts="$(backup_setting "${environment}" BACKUP_SFTP_KNOWN_HOSTS_FILE)"
    [[ "$(stat -c '%a %U' "${identity}" 2>/dev/null)" == "600 root" ]] || die "${identity} must exist with mode 600 and owner root."
    [[ -s ${known_hosts} ]] || die "${known_hosts} must hold the verified host key of the SFTP target."
    [[ "$(backup_setting "${environment}" BACKUP_SFTP_PATH)" =~ ^/[A-Za-z0-9._/-]*$ ]] || die "BACKUP_SFTP_PATH must be an absolute path of letters, digits, dot, dash, underscore and slash."
    [[ "$(backup_setting "${environment}" BACKUP_SFTP_PORT 22)" =~ ^[0-9]+$ ]] || die "BACKUP_SFTP_PORT must be a number."
}

sftp_base() {
    local path
    path="$(backup_setting "$1" BACKUP_SFTP_PATH)"
    printf '%s/%s' "${path%/}" "$1"
}

sftp_batch() {
    local environment="$1"
    sftp -q -b - \
        -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=20 \
        -o UserKnownHostsFile="$(backup_setting "${environment}" BACKUP_SFTP_KNOWN_HOSTS_FILE)" \
        -i "$(backup_setting "${environment}" BACKUP_SFTP_IDENTITY_FILE)" \
        -P "$(backup_setting "${environment}" BACKUP_SFTP_PORT 22)" \
        "$(backup_setting "${environment}" BACKUP_SFTP_USER)@$(backup_setting "${environment}" BACKUP_SFTP_HOST)"
}

sftp_listing() {
    local environment="$1" directory="$2" output
    output="$(printf -- '-ls -1 %s\n' "${directory}" | sftp_batch "${environment}")"
    grep -v '^sftp>' <<<"${output}" | sed 's|.*/||' | grep -v '^$' || true
}

sftp_put() {
    local environment="$1" set="$2" file="$3" base name root
    base="$(sftp_base "${environment}")"
    root="$(backup_setting "${environment}" BACKUP_SFTP_PATH)"
    name="$(basename "${file}")"
    sftp_batch "${environment}" >/dev/null <<BATCH
-mkdir ${root%/}
-mkdir ${base}
-mkdir ${base}/${set}
put ${file} ${base}/${set}/${name}.partial
rename ${base}/${set}/${name}.partial ${base}/${set}/${name}
BATCH
}

sftp_list_sets() {
    local environment="$1" listing name
    listing="$(sftp_listing "${environment}" "$(sftp_base "${environment}")")"
    while IFS= read -r name; do
        if backup_is_set_name "${environment}" "${name}"; then
            printf '%s\n' "${name}"
        fi
    done <<<"${listing}"
}

sftp_list_files() {
    local environment="$1" set="$2" listing name
    listing="$(sftp_listing "${environment}" "$(sftp_base "${environment}")/${set}")"
    while IFS= read -r name; do
        if [[ ${name} =~ ${BACKUP_SAFE_NAME} && ${name} != *.partial ]]; then
            printf '%s\n' "${name}"
        fi
    done <<<"${listing}"
}

sftp_get() {
    local environment="$1" set="$2" name="$3" destination="$4"
    printf 'get %s/%s/%s %s\n' "$(sftp_base "${environment}")" "${set}" "${name}" "${destination}" | sftp_batch "${environment}" >/dev/null
}

sftp_delete_set() {
    local environment="$1" set="$2" directory
    directory="$(sftp_base "${environment}")/${set}"
    printf -- '-rm %s/*\nrmdir %s\n' "${directory}" "${directory}" | sftp_batch "${environment}" >/dev/null
}
