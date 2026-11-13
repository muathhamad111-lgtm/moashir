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
export MAIL_LOG_CHANNEL=mail
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
  verify-phone)
    EMAIL="${2:-}"
    if [[ ! "$EMAIL" =~ ^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+\.example\.sa$ ]]; then
      echo "Usage: $0 verify-phone <e-mail of a fictional example.sa integration account>" >&2
      exit 1
    fi
    exec php artisan tinker --execute "App\\Models\\Identity\\User::query()->where('email', '$EMAIL')->firstOrFail()->forceFill(['phone_verified_at' => now()])->save();"
    ;;
  open-accredited)
    EMAIL="${2:-}"
    if [[ ! "$EMAIL" =~ ^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+\.example\.sa$ ]]; then
      echo "Usage: $0 open-accredited <e-mail of a fictional example.sa entity account>" >&2
      exit 1
    fi
    exec php artisan tinker --execute "\$entity = App\\Models\\Entities\\Entity::query()->findOrFail(App\\Models\\Identity\\User::query()->where('email', '$EMAIL')->firstOrFail()->entity_id); \$payment = App\\Models\\Finance\\Payment::factory()->paid()->state(['mode' => App\\Enums\\Integrations\\IntegrationMode::Live, 'purpose' => App\\Enums\\Finance\\PaymentPurpose::Accredited])->forEntity(\$entity)->create(); Illuminate\\Support\\Facades\\DB::transaction(fn () => app(App\\Services\\Finance\\ReceiptIssuer::class)->issue(\$payment->load('entity'))); echo app(App\\Actions\\Assessments\\OpenPaidAccreditedRequest::class)->handle(\$payment->id)?->id;"
    ;;
  *)
    echo "Usage: $0 setup|reset|serve|queue|schedule|verify-phone <e-mail>|open-accredited <e-mail>" >&2
    exit 1
    ;;
esac
