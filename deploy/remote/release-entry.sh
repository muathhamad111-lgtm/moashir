#!/usr/bin/env bash
set -Eeuo pipefail

source /opt/moashir/deploy/lib/common.sh

readonly ACTION="${1:-}"
readonly ENVIRONMENT="${2:-}"

require_root
assert_environment "${ENVIRONMENT}"

case "${ACTION}" in
    activate)
        [[ $# -eq 3 ]] || die "activate needs the environment and the release id."
        assert_release_id "$3"
        exec /usr/bin/bash "${MOASHIR_KIT}/remote/activate-release.sh" "${ENVIRONMENT}" "$3"
        ;;
    smoke | rollback)
        [[ $# -eq 2 ]] || die "${ACTION} needs the environment only."
        exec /usr/bin/bash "${MOASHIR_KIT}/remote/${ACTION}.sh" "${ENVIRONMENT}"
        ;;
    *) die "Unknown action ${ACTION}; allowed: activate, smoke, rollback." ;;
esac
