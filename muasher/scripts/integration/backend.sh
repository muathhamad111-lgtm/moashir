#!/usr/bin/env bash
set -euo pipefail

BACKEND_DIR="$(cd "$(dirname "$0")/../../../backend" && pwd)"
SPA_URL="${INTEGRATION_SPA_URL:-http://localhost:5180}"
API_PORT="${INTEGRATION_API_PORT:-8010}"
DATABASE="${INTEGRATION_DB_DATABASE:-moashir_integration}"

case "$DATABASE" in
  moashir | *_testing | *_testing_test_*)
    echo "Refusing to run the integration backend against '$DATABASE'." >&2
    exit 1
    ;;
esac

export APP_ENV=local
export APP_URL="$SPA_URL"
export FRONTEND_URL="$SPA_URL"
export SANCTUM_STATEFUL_DOMAINS="${SPA_URL#*://}"
export CORS_ALLOWED_ORIGINS="$SPA_URL"
export DB_DATABASE="$DATABASE"
export SESSION_DRIVER=database
export SESSION_SECURE_COOKIE=false
export CACHE_STORE=database
export QUEUE_CONNECTION=database
export LOG_CHANNEL=stderr
export MAIL_MAILER=log
export MAIL_LOG_CHANNEL=stderr
export FILES_SCANNER=fake
export PDF_RENDERER=fake
export SMS_PROVIDER=fake
export INTEGRATIONS_ENV_FALLBACK=false

cd "$BACKEND_DIR"

case "${1:-}" in
  setup) php artisan migrate --seed --no-interaction ;;
  reset) php artisan migrate:fresh --seed --no-interaction --confirm-database="$DATABASE" ;;
  serve) exec php artisan serve --host=127.0.0.1 --port="$API_PORT" --no-reload ;;
  queue) exec php artisan queue:work --sleep=1 --tries=3 ;;
  schedule) exec php artisan schedule:work ;;
  *)
    echo "Usage: $0 setup|reset|serve|queue|schedule" >&2
    exit 1
    ;;
esac
