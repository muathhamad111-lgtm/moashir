# Moashir API (backend)

Laravel 13 API for the Moashir platform (منصة مؤشر). It serves JSON only under `/api/v1` and is
consumed by the Vue 3 SPA through Sanctum SPA cookie authentication. Architecture, guardrails and
contracts live in the repository root (`CLAUDE.md`, `references/`, `agents/analysis/`).

## Stack

| Concern | Choice (pinned in `composer.json`) |
|---|---|
| Runtime | PHP 8.4+ (8.4 in Sail, 8.5 verified locally), Laravel 13.39 |
| Database | PostgreSQL 17 (Sail) — PostgreSQL 16 accepted for local development |
| Auth | Sanctum SPA cookies (bearer tokens disabled), Fortify TOTP (headless, routes ignored) |
| Authorization | spatie/laravel-permission 8 (code-defined catalog) + Policies, Super Admin via `Gate::before` |
| Audit | owen-it/laravel-auditing 14, extended with `reason`, `request_id`, `actor_roles`, `actor_entity_id`; append-only |
| Queues / cache | Redis + Horizon (Sail, staging, production); `database` drivers as the local fallback |
| Errors | Ignition locally; self-hosted GlitchTip through `sentry/sentry-laravel` (PII scrubbed) |
| Headers | `SetSecurityHeaders` middleware + spatie/laravel-csp (`default-src 'none'; frame-ancestors 'none'`) |
| Backups | spatie/laravel-backup (encrypted archive, disk from `BACKUP_DISK`) |
| PDF documents | chrome-php/chrome 1.16.1 driving a pinned Chrome for Testing `chrome-headless-shell`; QR codes with bacon/bacon-qr-code 3.1.1 |
| Quality | Pest 5 (architecture, unit, feature), Larastan level max, Pint, Rector, `composer audit` |

## Setup

### Option A — Sail (team default: PHP 8.4, PostgreSQL 17, Redis 7)

1. `cp .env.example .env` and fill the values (see the table below). Required for Sail:
   `DB_HOST=pgsql`, `DB_DATABASE=moashir`, `DB_USERNAME`, `DB_PASSWORD`, `REDIS_HOST=redis`,
   `REDIS_PASSWORD`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=database`.
2. `composer install`
3. `./vendor/bin/sail up -d`
4. `./vendor/bin/sail artisan key:generate`
5. `./vendor/bin/sail artisan migrate --seed`
6. `./vendor/bin/sail artisan horizon` (queues)

The `pgsql` container creates `moashir_testing` on first start (`docker/pgsql`). The compose file
refuses to start without `DB_PASSWORD` and `REDIS_PASSWORD`; it contains no default credentials.

### Option B — local fallback (no Docker, no Redis)

1. Install PHP 8.4+ with `pdo_pgsql`, `fileinfo` and `zip` (evidence bundles), and PostgreSQL 16 or 17.
2. `createdb moashir && createdb moashir_testing`
3. `cp .env.example .env` and set at least:
   `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8000`, `APP_LOCALE=ar`,
   `APP_FALLBACK_LOCALE=ar`, `FRONTEND_URL=http://localhost:5173`,
   `CORS_ALLOWED_ORIGINS=http://localhost:5173`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:8000`,
   `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=moashir`, `DB_USERNAME`,
   `DB_PASSWORD`, `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=false`, `CACHE_STORE=database`,
   `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`, `MAIL_FROM_ADDRESS`, `SMS_PROVIDER=fake`, and, without a local
   clamd, `FILES_SCANNER=fake` (local only, see "Local-only fakes"). PDFs: `PDF_RENDERER=fake` (placeholder files) or
   `PDF_RENDERER=chromium` with `PDF_CHROME_BINARY` pointing to a local `chrome-headless-shell` of the pinned version
   (`npx @puppeteer/browsers install chrome-headless-shell@<version>` or the Chrome for Testing zip), see "PDF documents".
4. `composer install && php artisan key:generate && php artisan migrate --seed`
5. `php artisan serve` and, in another terminal, `php artisan queue:work` (OTP, invitations and alerts
   are queued; with `MAIL_MAILER=log` the messages appear in `storage/logs/laravel.log`).

`migrate --seed` loads the code-defined permission catalog (88 keys, 9 roles), the curated breached-password
seed (see below), publishes criteria version 1 (see "Criteria") and installs the 13 regions with their cities (see
"Entities"). In `local` only it also
creates fictional staff accounts (`*@moashir.example.sa`, no password): sign in with the e-mail OTP
mode, then enrol TOTP when asked. It also publishes sample local terms and privacy texts (`LocalTermsSeeder`, local
only) so that entity registration can be tried; on an existing local database run
`php artisan db:seed --class=LocalTermsSeeder`.

### First Super Admin (console only)

The Super Admin is never created or changed over HTTP. The technical owner runs, on the server:

```
SUPER_ADMIN_INITIAL_EMAIL=<address> php artisan moashir:super-admin grant --name="<full name>"
php artisan moashir:super-admin list
php artisan moashir:super-admin revoke --email=<address> [--allow-last]
```

`grant` asks for the full e-mail as a typed confirmation, refuses users holding roles (SoD-8), entity
accounts, inactive users and more than `SUPER_ADMIN_MAX_ACCOUNTS` (default 2); it resets TOTP, ends the
account's sessions, writes the audit event `security.super_admin.granted` (with the OS user) and alerts
every Super Admin (SEC-03). A new initial account receives a password link. `list` prints masked
addresses only. A PostgreSQL trigger rejects any change of `users.is_super_admin` that does not come from
the command's transaction (`moashir.console_context`).

### Database roles (staging / production)

Run `database/sql/production-roles.sql` with `psql` as the database superuser **after** the first
migration. The script is idempotent (roles are created only when missing, the grants run in one transaction) and takes
optional psql variables for several environments on one cluster: `-v database=… -v owner_role=… -v app_role=…
-v maintenance_role=…` (defaults `moashir`, `moashir_owner`, `moashir_app`, `moashir_maintenance`) and
`-v skip_password_prompts=1` when the passwords are set separately (the deployment kit sets SCRAM verifiers generated on
the server). It also revokes every database privilege from `PUBLIC`. It creates `moashir_owner` (schema owner, migrations), `moashir_app` (runtime; INSERT/SELECT only
on `audits`, `authentication_events`, `terms_acceptances`) and `moashir_maintenance` (audit retention),
and prompts for each password (never stored in the repository). `BEFORE UPDATE/DELETE/TRUNCATE` triggers
protect the same tables for every role, the model layer refuses updates and deletes too.

### Breached-password list (local, no network)

Every new password (invitation acceptance, password reset, password change, and entity registration once the
Entities domain adds it through the same `PasswordPolicy`) is checked against a breached-password list stored in
our own database. The check never leaves the server: `LocalBreachedPasswordChecker` (bound to the
`BreachedPasswordChecker` contract) hashes the candidate with SHA-256 and looks the digest up in the
`breached_passwords` table (primary key, one indexed lookup). No external service is called, no environment
variable switches it off, and the table holds digests only, never a password. Matching is exact (case-sensitive).

- **Bundled seed** — `database/data/breached-passwords/curated-seed.sha256`: 112 SHA-256 digests of well-known weak
  passwords (digit runs, keyboard walks on Latin and Arabic layouts, `password` / `admin` / `welcome` variants,
  city, country, season and year patterns, common first name + digits patterns). Authored by the project team for
  this repository, no third-party content, so it ships under the project licence. Loaded by `BreachedPasswordSeeder`
  (part of `db:seed`, idempotent) in local and testing. Seeding is refused in staging and production, so there the
  same file is loaded with the import command below (`--format=sha256`).
- **Bundled breached list (Task-51, SEC-19)** — `database/data/breached-passwords/seclists-xato-top-1m-min12.sha256`:
  46,296 SHA-256 digests, one per line, sorted. Source: SecLists (https://github.com/danielmiessler/SecLists),
  file `Passwords/Common-Credentials/xato-net-10-million-passwords-1000000.txt` (the 1,000,000 most frequent
  passwords of Mark Burnett's 2015 "ten million passwords" data set, which its author released to the public domain;
  SecLists itself is MIT, © 2018 Daniel Miessler — the licence text ships beside the list as `SECLISTS-LICENSE.txt`).
  Only entries of 12 to 128 characters are kept, because the policy refuses shorter or longer passwords anyway; so
  the file covers every candidate of the top million that our policy could otherwise accept. Only digests are
  committed, never a plain password. Built offline on 2026-11-04 from SecLists commit `c205c36` (file SHA-256
  `424a3e03…0caf51`); rebuild = keep the lines of 12–128 UTF-8 characters, SHA-256 each line, sort, de-duplicate.
- **Import** — one command for a single list or for the whole bundled directory:

  ```
  php artisan moashir:breached-passwords:import database/data/breached-passwords     # every *.sha256 file of the directory
  php artisan moashir:breached-passwords:import /path/on/server/list.txt              # one password per line
  php artisan moashir:breached-passwords:import /path/on/server/list.sha256 --format=sha256   # one hex digest per line
  ```

  The command streams each file in chunks of 1,000 lines, skips blank lines, ignores entries already listed (safe to
  re-run) and reports `file: read / added / already listed / rejected`. Deployments should import the directory (the
  deploy kit imports `curated-seed.sha256` only until it switches to the directory form). Delete any plain-text file
  from the server after an import. The Have I Been Pwned dataset is not used (operator decision 2026-10-31: no Have I
  Been Pwned and no external service).

## Environment variables

`.env.example` lists every name with no value. Variables specific to Moashir:

| Name | Purpose |
|---|---|
| `FRONTEND_URL` | Base URL of the SPA used in e-mailed links (invitation, password reset) |
| `CORS_ALLOWED_ORIGINS` | Comma-separated SPA origins (same origin in production) |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts allowed to use the session cookie |
| `SESSION_IDLE_SUPER_ADMIN_MINUTES`, `SESSION_IDLE_STAFF_MINUTES`, `SESSION_IDLE_ENTITY_MINUTES`, `SESSION_ABSOLUTE_HOURS` | Idle (15 / 30 / 60) and absolute (12 h) session timeouts |
| `AUTH_OTP_EXPIRY_SECONDS`, `AUTH_OTP_RESEND_COOLDOWN_SECONDS`, `AUTH_OTP_MAX_ATTEMPTS` | OTP policy (300 s, 60 s, 5) |
| `AUTH_LOCKOUT_MAX_ATTEMPTS`, `AUTH_LOCKOUT_MINUTES` | Lockout policy (5 attempts, 15 min) |
| `AUTH_PASSWORD_RESET_TTL_MINUTES`, `AUTH_INVITATION_TTL_HOURS` | Reset link (60 min) and invitation (72 h) validity |
| `SUPER_ADMIN_INITIAL_EMAIL`, `SUPER_ADMIN_MAX_ACCOUNTS` | Console bootstrap of the first Super Admin; account cap |
| `SECURITY_ALERT_MAIL` | Security mailbox that receives SEC-xx alerts in addition to the Super Admins |
| `SECURITY_HSTS_MAX_AGE` | HSTS max-age sent on HTTPS responses |
| `SENTRY_LARAVEL_DSN`, `SENTRY_ENVIRONMENT`, `SENTRY_RELEASE`, `SENTRY_TRACES_SAMPLE_RATE` | Self-hosted GlitchTip |
| `SMS_PROVIDER` | `fake` keeps the in-memory gateway; honoured in `local` and `testing` only and refused elsewhere (the settings gateway is used and `platform.fake_adapter_refused` is logged as critical, see "Local-only fakes"); any other value uses the settings gateway, and `taqnyat` or `msegat` names the provider of the environment bootstrap |
| `INTEGRATIONS_ENV_FALLBACK` | `true` lets the environment values below act as a bootstrap while no integration row exists for a kind (see "Integrations") |
| `MOYASAR_ENV`, `MOYASAR_PUBLISHABLE_KEY`, `MOYASAR_SECRET_KEY`, `MOYASAR_WEBHOOK_SECRET` | Payment bootstrap (optional) |
| `MOYASAR_FORM_SCRIPT_URL`, `MOYASAR_FORM_SCRIPT_INTEGRITY` | Moyasar form script handed to the SPA as `checkout.script { url, integrity }` (default URL `https://cdn.moyasar.com/mpf/1.15.0/moyasar.js`, no integrity); see "Checkout script and SRI" |
| `TAQNYAT_API_TOKEN`, `TAQNYAT_SENDER`, `MSEGAT_USERNAME`, `MSEGAT_API_KEY`, `MSEGAT_SENDER` | SMS bootstrap (optional) |
| `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL` | AI provider bootstrap (optional, `openai` or `anthropic`) |
| `SERVER_EGRESS_IPS` | Comma-separated public egress addresses of the server, shown on the settings screens for the providers' IP allow-lists |
| `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_DISK`, `BACKUP_NOTIFICATION_MAIL` | Encrypted off-server backups |
| `HEALTH_ALLOWED_IPS` | Comma-separated addresses or CIDR ranges that receive the per-check details of `GET /health/ready` (monitoring host) |
| `HEALTH_MIN_FREE_DISK_MB` | Free space below which the readiness disk check fails (default 1024) |
| `FILES_DISK` | Laravel disk of uploaded and generated files (default `local` = `storage/app/private`, outside the web root) |
| `FILES_SCANNER` | `fake` marks every file clean except the test signature; honoured in `local` and `testing` only and refused elsewhere (ClamAV is used and `platform.fake_adapter_refused` is logged as critical); unset = ClamAV |
| `FILES_CLAMAV_SOCKET`, `FILES_CLAMAV_TIMEOUT_SECONDS` | clamd address (`unix:///var/run/clamav/clamd.ctl` or `tcp://127.0.0.1:3310`) and timeout (30 s) |
| `FILES_ORPHAN_GRACE_HOURS` | Age after which a stored file never attached to an owner is purged (default 24 hours, see "File housekeeping") |
| `PDF_RENDERER` | `chromium` prints certificates, feedback reports and receipts with the headless shell below; `fake` renders placeholder PDFs, honoured in `local` and `testing` only and refused elsewhere (logged like `FILES_SCANNER`); unset = no renderer, downloads answer 503 `document.renderer_unavailable` |
| `PDF_CHROME_BINARY` | Absolute path of the pinned Chrome for Testing `chrome-headless-shell` binary (server: `/opt/moashir/chrome-headless-shell/current/chrome-headless-shell-linux64/chrome-headless-shell`); a missing or non-executable path keeps the renderer unavailable and logs `documents.chrome_binary_unusable` (critical) |
| `PDF_RENDER_TIMEOUT_SECONDS` | Timeout of each browser step while printing (default 60) |
| `ASSESSMENT_SCALE_KIND` | Assessment scale copied into a criteria version when it is published: `of_10` (default) or `relative`; seam until the Settings domain stores it |
| `ENTITY_EMAIL_CHANGE_POLICY` | Official e-mail change of an entity: `requires_approval` (default) or `immediate_notify` (Q-103); seam until the Settings domain stores it |
| `ENTITY_VERIFICATION_REMINDER_COOLDOWN_MINUTES` | Minimum time between two verification reminders of the same unverified registration (default 15) |
| `APP_DEMO_DATA` | `true` allows `moashir:demo:install` and `moashir:demo:rotate-passwords` (review / demo environments only, see "Demo data") |
| `MOASHIR_DEMO` | Demo posture opt-in of the deploy kit (Task-52). Outside `local` / `testing` the demo commands also need `MOASHIR_DEMO=true`; in `production` without it the `log` / `array` mailers are refused (see "Mail posture") |
| `SESSION_COOKIE` | Session cookie base name, distinct per environment; it gets the `__Host-` prefix automatically when the cookie is secure, host-only (`SESSION_DOMAIN` empty) and on `/` (see "Session cookie") |
| `HORIZON_PREFIX`, `HORIZON_MAX_PROCESSES` | Horizon Redis key prefix and worker cap (staging default 3, production default 10) |
| `MAIL_LOG_CHANNEL`, `MAIL_LOG_DAYS` | With `MAIL_MAILER=log`: the `mail` channel writes messages to `storage/logs/mail-<date>.log` (mode 600, default 7 days) |

Framework variables (`APP_*`, `DB_*`, `REDIS_*`, `MAIL_*`, `SESSION_*`, `CACHE_*`, `QUEUE_*`, `LOG_*`) keep
their Laravel meaning. Production must use `APP_ENV=production`, `APP_DEBUG=false`,
`SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, Redis for cache and queues.

## API conventions

- Base path `/api/v1`, JSON only (`Accept: application/json`, otherwise 406 `request.not_acceptable`).
- Success: `{ "data": …, "meta": {…}, "errors": null }`; lists add `meta.pagination
  { page, per_page, total, last_page }` and, where relevant, `meta.stats`.
- Error: `{ "data": null, "meta": {}, "message": "<translated>", "code": "<stable code>", "errors": { "<field>": ["…"] } }`.
- snake_case keys, ISO 8601 timestamps (UTC), canonical enum values.
- Language: `Accept-Language` (`ar` | `en`, region and quality values honoured) → user profile → `locale` cookie →
  `ar`; response header `Content-Language` on every response. `SetLocale` is a global middleware, so every error —
  401 `unauthenticated`, 419 `csrf.token_mismatch`, 429 `rate_limited`, 404, 405, 406, 413, 503 — is translated
  before routing, CSRF, throttling and authentication run (header, then cookie, then `ar`). `ApplyUserLocale`
  (sorted right after `AuthenticatesRequests`) then applies the saved profile language when the request names no
  supported language. A header that names only unsupported languages falls through to the next step. The SPA
  sends `Accept-Language` from its language switch; a guest `locale` cookie is read as plain text.
- SPA flow: `GET /sanctum/csrf-cookie`, then `POST /api/v1/auth/login` with `X-XSRF-TOKEN`; the SPA
  document must send a `Referer`/`Origin` (use `Referrer-Policy: strict-origin-when-cross-origin` on the
  SPA, not `no-referrer`) so Sanctum recognises it as stateful.
- Every authenticated route carries a `can:` ability (enforced by `tests/Feature/Platform/RouteAuthorizationTest.php`).

### Identity endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/health` | public, `throttle:public` |
| GET | `/health/ready` | public status; details to `HEALTH_ALLOWED_IPS` or a System Administrator, `throttle:public` |
| GET | `/auth/login-modes` | public |
| POST | `/auth/login` (`identifier` + `password`) | public, `throttle:login` (5/min per identifier + IP, 20 per 10 min per identifier, 30/min per IP) |
| POST | `/auth/otp/request` (`identifier`, entity sign-in code) | public, `throttle:otp-request` (3 per 10 min per identifier, 10 per 10 min per IP) + per-account limits |
| POST | `/auth/otp/verify`, `/auth/otp/resend` | public, `throttle:otp-verify` / `throttle:otp-resend` + per-account limits |
| POST | `/auth/mfa/challenge` | pending sign-in, `throttle:mfa` |
| POST | `/auth/mfa/enroll`, `/auth/mfa/confirm` | pending enrolment, or signed in under `session.timeouts` (+ `current_password` to start); 403 `forbidden` for entity accounts (`identity.mfa.enroll`) |
| POST | `/me/phone/verification`, `/me/phone/verification/confirm` | signed in, `throttle:sensitive` |
| GET / PUT | `/settings/auth` | `settings.auth.manage` (System Administrator only, locked), `throttle:sensitive` on PUT |
| GET / POST | `/audit-logs`, `/audit-logs/{id}`, `/audit-logs/export` | `audit.view` / `audit.export` |
| POST | `/auth/password/forgot`, `/auth/password/reset` | public, `throttle:password-reset` |
| POST | `/auth/invitations/{token}/accept` | public, `throttle:invitation` |
| GET | `/auth/session` | signed in |
| POST | `/auth/logout`, `/auth/password/change` | signed in |
| POST / DELETE | `/auth/mfa/recovery-codes`, `/auth/mfa` | signed in + current password |
| GET / PATCH | `/me/preferences` | signed in |
| GET / DELETE | `/me/sessions`, `/me/sessions/{session}` | signed in (own sessions only) |
| GET | `/me/profile/permissions` | signed in |
| PUT | `/users/{id}/assessor-qualifications`, `/users/{id}/request-scope` | `can:update,managedUser` (`users.manage`), `throttle:sensitive` |
| POST | `/me/email/change`, `/me/email/confirm` | Super Admin only |
| GET / POST | `/users` | `users.view` / `users.manage` |
| GET / PUT | `/users/{user}` | `users.view` / `users.manage` |
| POST | `/users/{user}/suspend`, `/activate`, `/unlock`, `/invitation/resend`, `/mfa/reset` | `users.manage` |
| GET | `/roles` | `users.view` or `roles.matrix.view` |
| GET | `/permissions` | `roles.matrix.view` |
| PUT | `/permissions/{key}` (labels), `/roles/{role}/permissions` (matrix) | `roles.matrix.manage` |
| GET | `/search?q=&limit=` | `search.global` (any search provider available), `throttle:search` (60/min per user) |

Super Admin accounts answer 404 on every `/users/{user}` path for every caller and never appear in lists.

### Sign-in, sign-in codes and lockout (Task-51, Task-61; client decision 2026-11-05 supersedes SEC-12 revised)
- **Two ways in for an entity, one for staff.** An entity account signs in with its identifier and password
  (`POST /auth/login`) OR with a one-time sign-in code (`POST /auth/otp/request` then `POST /auth/otp/verify`). There is
  no second factor for entity accounts. Staff and the Super Admin sign in with the password followed by the mandatory
  authenticator app (TOTP); they never receive a sign-in code.
- **Password sign-in.** `POST /auth/login { identifier, password }`. The type of `identifier` is detected by its format
  (`Support\ValueObjects\SignInIdentifier`, rule `Rules\RecognisedSignInIdentifier`, shared by both endpoints): an e-mail
  (contains `@`, lower-cased); a Saudi mobile number (`05XXXXXXXX`, `5XXXXXXXX`, `+9665…`, `9665…`, `009665…`, spaces
  and dashes ignored, normalised to `+9665XXXXXXXX`); or the entity's unified national number (10 digits starting with 7,
  resolves to the entity's owner account). Anything else is 422 on `identifier` (format message, says nothing about
  accounts). Every failure — unknown account, wrong password, unverified mobile, account without a password — answers
  the same 422 `credentials.invalid`; the timing is equalised with a dummy hash. The previous form's `email` field is
  still accepted as an alias of `identifier` on this endpoint only (transitional; remove
  `LoginRequest::LEGACY_EMAIL_FIELD` once no client sends it); `mode` / `channel` are ignored. Outcome: an active entity
  → `step: authenticated` directly (`method: password`); a pending-verification entity → `step: otp_required` with its
  registration code by e-mail (purpose `login`); staff / Super Admin → `mfa_challenge` or `mfa_enrollment`.
- **Mobile sign-in needs a verified, unique number.** `users.phone` stays encrypted; `users.phone_index` holds a keyed
  blind index (HMAC-SHA256 with a key derived from `APP_KEY`, purpose-separated, `Services\Identity\BlindIndex`),
  recomputed by `Observers\Identity\UserPhoneIndexObserver` whenever the number changes (a change also clears
  `phone_verified_at`). A partial unique index allows each number once among verified, non-deleted accounts
  (`users_verified_phone_index_unique`); unverified duplicates are allowed so nobody can block someone else's number by
  typing it. A signed-in user verifies the stored number by SMS: `POST /me/phone/verification` → `OtpChallenge`
  (channel `sms`; 409 `phone.missing` / `phone.already_verified`, 503 `phone.sms_unavailable` — never an e-mail
  fallback), then `POST /me/phone/verification/confirm { challenge_id, code }` → `{ phone_verified, phone_verified_at }`
  (409 `phone.in_use` when another account already verified it). The own entity resource shows `phone_verified`. After
  rotating `APP_KEY`, run `php artisan moashir:identity:rebuild-phone-index` (the migration that adds the column runs
  the same backfill).
- **Sign-in code request.** `POST /auth/otp/request { identifier }` (`Actions\Identity\RequestSignInCode`) always answers
  `200 { step: "otp_required", requires_otp: true, otp: { challenge_id, channel, length, expires_at,
  resend_available_at }, message }` with `message` = `messages.sign_in_code_sent` ("If an entity account matches what
  you entered, a sign-in code has been sent."). The only other answers are 422 (identifier format, same message as
  `/auth/login`) and 429 `rate_limited`; never 403, 404, 423 or 429 `otp.cooldown`, and never a destination.
  - Channel rule R-CH (`Services\Identity\SignInCodeChannelResolver`): the code follows what was typed. A mobile number
    gets an SMS when SMS is allowed in `auth.entity_sign_in_code_channels`, the SMS gateway is available
    (`SmsAvailability`) and the hourly sign-in SMS cap (`moashir.auth.otp.sign_in_sms_per_hour`, default 100,
    `Services\Identity\SignInSmsCap`) is not reached; otherwise e-mail. An e-mail or a unified national number always
    gets an e-mail to the official address. The channel is computed from the input and global state only, before the
    account is looked at, so a decoy gets the same `channel` as a real account.
  - Eligibility (`Services\Identity\SignInCodeEligibility`, verdict enum `SignInCodeVerdict`): an active entity account
    (status active, not locked, entity active, under the per-account limits) gets a code with purpose `sign_in_code`;
    a pending-verification entity gets its registration code (purpose `login`) by e-mail whatever the setting. Everyone
    else gets a decoy challenge (`DecoyOtpChallenges`, the channel is stored with it) and nothing is sent: unknown
    identifier, staff, Super Admin, suspended or deactivated account, deleted entity, locked account, per-account limit
    reached. The request is recorded as `authentication_events.type = otp_request_ignored` with `meta.reason` =
    `unknown | not_entity | inactive | locked | throttled` (unknown identifiers as `identifier_type` + keyed hash).
  - SMS only goes to a verified mobile: `IssueOtpChallenge` texts an unverified number only for `verify_phone`; any other
    purpose falls back to e-mail. A permanent SMS failure falls back to e-mail and raises INT-01 (`FallsBackToMail`).
    Decoys read the hourly cap but never use it up. The SMS body is the existing `otp_code` template.
- **Verify and resend.** `POST /auth/otp/verify { challenge_id, code }` accepts the purposes `login` and `sign_in_code`
  (a `sign_in_code` challenge whose user is not an entity account answers `otp.challenge_invalid`). A `sign_in_code`
  success signs the entity in through `EstablishSession` (session regenerated, failed counter reset) and records
  `login_success` with `meta { method: "sign_in_code", channel }`; it never touches `email_verified_at`. A `login`
  success verifies the e-mail, activates a pending entity and signs it in (`method: otp`). `POST /auth/otp/resend`
  (60 s cooldown, 429 `otp.cooldown`) issues a new code on the stored channel and voids the old one; SMS falls back to
  e-mail when it is no longer allowed or available, the cap is reached, or the mobile is no longer verified. A decoy
  resend keeps its stored channel under the same global fallbacks.
- **Entity sign-in code channels = a control-panel setting** `auth.entity_sign_in_code_channels` (json list of
  `email` / `sms`, default `["email"]`): `GET /settings/auth` → `{ entity_sign_in_code_channels: ["email"] |
  ["email","sms"], sms_status: "unavailable" | "test" | "live", staff_totp_required: true }`; `PUT /settings/auth {
  entity_sign_in_code_channels, reason? }`. Validation: required non-empty list, distinct, values `email` / `sms`;
  e-mail must stay in the list (422 `entity_sign_in_code_channels` "E-mail must stay allowed…", operator Q-151); `sms`
  is refused while the SMS gateway is not configured (422 "Configure the SMS gateway…"). A saved `sms` stays when the
  gateway later disappears; R-CH then uses e-mail. `settings.auth.manage` is hard-locked to the System Administrator
  (Super Admin by bypass with a `reason` of 10..1000, else 422 `reason_required`, plus SEC-04). A real change is audited
  (`settings.auth_updated`, lists before / after, reason) and raises **SEC-07 "Entity sign-in code channels changed"**
  to every System Administrator, every Super Admin and the security mailbox; no change writes and sends nothing.
  Single-factor entity sign-in (password OR code) is a client-accepted deviation from NCA ECC 2-15-3-5 (handover
  security matrix).
- **Entity accounts cannot hold an authenticator.** `POST /auth/mfa/enroll` and `/auth/mfa/confirm` answer 403
  `forbidden` for entity accounts (ability `identity.mfa.enroll`, `Policies\Identity\MfaEnrollmentPolicy`). Migration
  `2026_11_05_140200` cleared the TOTP data of every entity account (one `mfa_disabled` event each, `meta.reason:
  entity_second_factor_cancelled`). Staff enrolment is unchanged.
- **One lockout for every factor (SEC-08, SEC-09).** Wrong passwords, wrong sign-in and registration codes and wrong TOTP /
  recovery codes all increment the same `users.failed_login_attempts`; at 5 the account is locked 15 minutes (423
  `account_locked` + `Retry-After`), `locked` is recorded, and USR-02 goes to the user and every System Administrator.
  A locked account cannot verify a code or a TOTP from any session or address. A successful sign-in resets the counter.
  Codes are also limited per account: 5 per 10 minutes and 20 per day (`otp.per_user_per_ten_minutes`,
  `otp.per_user_per_day`; 429 `otp.cooldown` on password sign-in and resend, a silent decoy on `/auth/otp/request`), on
  top of the per-address limiters. A locked account gets no sign-in code (decoy, reason `locked`); verify and resend
  answer 423; the password path answers 423 (known pre-existing signal G-6, kept for the security matrix).
- **Accounts without a password** (created from the console, e.g. the Super Admin before the first-password link is
  used) cannot sign in; they set a password through `POST /auth/password/forgot` → the e-mailed reset link.
- **MFA enrolment (SEC-23).** `POST /auth/mfa/enroll` and `/auth/mfa/confirm` run under `session.timeouts`; a
  signed-in user must also send `current_password` to start an enrolment. The pending-sign-in enrolment of staff is
  unchanged; entity accounts get 403 (above).
- **`GET /auth/login-modes`** → `{ identifiers: ["email", "mobile", "unified_number"], code_channels: ["email"] |
  ["email","sms"], otp_length, otp_expiry_seconds, resend_cooldown_seconds, sso_providers: [] }`. `code_channels` lists
  the allowed channels that are also usable now (SMS only while allowed and the gateway is available). The
  `AUTH_LOGIN_*` variables are gone.
- **Historical audit values.** `authentication_events.meta.method = email_second_factor` and older
  `settings.auth_updated` rows on `auth.entity_second_factor_enabled` stay untouched (append-only); they describe the
  retired Task-51 entity second factor.
- **Failed attempts on unknown accounts (SEC-18).** `authentication_events` keeps `identifier_type` and
  `identifier_hash` (HMAC-SHA256, keyed, purpose `sign_in_identifier`) instead of the typed address or number; new rows
  never fill `email_attempted`. Retention: the table is append-only for the application role (trigger + grants); only
  the maintenance DB role may delete. Proposed periods (to be confirmed by the client's data office, then run as a
  scheduled maintenance-role job — not built yet): rows about unknown identifiers 12 months (the NCA ECC 2-12 minimum
  for security event logs), every other row with the audit trail (Q-76: archived encrypted after 5 years, then
  removed). Rows written before Task-51 may still hold a clear `email_attempted`: they are removed by that job, or
  earlier with the maintenance role on a data-subject request. An investigator compares the HMAC of a candidate
  value; after an `APP_KEY` rotation older hashes no longer match.

### Session cookie (SEC-21)
`config/session.php` names the session cookie through `Support\Security\SessionCookieName`: when the cookie is
`Secure`, has no `Domain` and uses path `/`, the configured name gets the `__Host-` prefix (for example
`__Host-moashir_production_session`), so a sibling host such as the staging subdomain can never set or overwrite it;
otherwise (local HTTP) the plain name is kept. Staging and production keep different names and, being host-only,
separate cookies. The Sanctum CSRF flow is unchanged: the SPA still calls `GET /sanctum/csrf-cookie` and sends the
`XSRF-TOKEN` cookie back as `X-XSRF-TOKEN` (that cookie keeps its name; a tossed value only fails the CSRF check).
Nothing changes in the SPA; it never reads the session cookie.

### Token redaction (SEC-17)
Single-use tokens never reach the audit trail, the application logs or GlitchTip: `Support\Security\SensitiveUrl`
replaces the token of `/auth/invitations/{token}` and `/auth/reset-password/{token}` and any path segment of 32+
random characters with `[redacted]`. It is applied to `audits.url` (`AuditRecorder`, `UrlWithoutQueryResolver`), to
every application log channel (`tap` `RedactSensitiveLogData`, message and context; the demo-only `mail` channel of the
`log` mailer is left as is), and to GlitchTip events and transactions (`SentryEventScrubber`, also
`before_send_transaction`). The Nginx access log is masked by the deploy kit (Task-52).

### Database guards and the console context (SEC-27, residual risk)
The triggers that protect `users.is_super_admin`, entity erasure and criteria corrections accept a change only inside
a transaction that set `moashir.console_context` / `moashir.entity_erasure` / the criteria flag with `set_config`.
The application DB role can set those values too, so the guards stop application bugs (any code path other than the
dedicated writer) but not SQL injection or a stolen application DB password. No simple, safe fix exists inside the
application: a real separation needs a dedicated `moashir_console` DB role used only by these console commands, with
the triggers testing `current_user`, and credentials held outside the web / worker processes (deploy-kit change,
proposed for the in-Kingdom host). Mitigations today: bound queries only (no SQL concatenation), Super Admin cap and
SEC-03 alert on every console grant, append-only audit rows, least-privilege `production-roles.sql`.

### Global search

`GET /api/v1/search?q=<term>&limit=<1..10, default 5>` powers the back-office header search. The term is
normalized for Arabic (diacritics and tatweel removed; أ إ آ ٱ → ا; ى ئ → ي; ؤ → و; ة → ه; Eastern Arabic and
Persian digits → 0-9; lower case; spaces collapsed) by `SearchTextNormalizer` in PHP and by the matching PostgreSQL
function `moashir_search_normalize(text)` on the column side; a unit test keeps both identical. It must keep at
least 2 searchable characters (422 `validation_failed` on `q` otherwise); `%` and `_` are matched literally.

Each domain contributes a provider (`App\Contracts\Search\SearchProvider`, tagged `search.providers` in
`SearchServiceProvider`). The response lists one group per provider the user may use, in this order:

| Group | Provider | Visible to | Status |
|---|---|---|---|
| `entities` | `Services\Entities\EntitySearchProvider` | `entities.view` | live: entities by normalized Arabic or English name or official e-mail, within the entity record scope; never deleted or erased entities. Item `{ id, reference, name{ar,en}, status }` |
| `requests` | `Services\Assessments\RequestSearchProvider` | `requests.queue.view` or `requests.assigned.view` | live: assessments by request number (`SLF-2026-000001`) or by the normalized name / official e-mail of their entity, within the assessment record scope (System Administrator and Requests Follower: all; an assessor: the accredited and advanced requests assigned to them, current or completed assignment; others: none); never frozen requests. Advanced statuses follow the announcement mask (see "Award cycles and the advanced path"). Item `{ id, number, path, status, is_frozen, entity{ id, reference, name{ar,en}, status } }` |
| `users` | `Services\Identity\UserSearchProvider` | `users.view` (`UserPolicy::viewAny`) | live: staff users by name or e-mail; never Super Admin or entity accounts |

A user without any of these permissions receives 403 `forbidden`. Filtering happens on the server only.

```
{
  "data": [
    { "type": "entities", "total": 0, "items": [] },
    { "type": "requests", "total": 0, "items": [] },
    { "type": "users", "total": 1, "items": [
      { "id": 14, "name": "أحمد العتيبي", "email": "ahmed.alotaibi@moashir.example.sa", "status": "active", "roles": ["assessor"] }
    ] }
  ],
  "meta": { "limit": 5 },
  "errors": null
}
```

To add a domain: implement `SearchProvider` (permission check in `isAvailableTo`, record scope in the query,
safe fields only in its `SearchHit` DTO) and add it to the tag list. The search term travels in the query string,
so the Nginx access log for `/api/v1/search` must not record query strings (see Deployment notes).

## Criteria (build step 2)

Criteria are versioned: `criteria_versions` (`draft → published → archived`) own `main_criteria → sub_criteria →
guiding_points` and `attachment_requirements` (sub level or point level). There is at most ONE published version and
ONE open draft (partial unique indexes). Every structural edit happens on the draft; publishing archives the previous
version. Weights are `numeric(10,2)` and travel as decimal strings with two places (`"133.34"`), like money; the
domain never uses floats (`Weight` value object in hundredths).

Rules enforced by the API (and by PostgreSQL triggers as the last line of defence):

- **Remaining-weight guard (blocks):** an active sub-criterion never exceeds `main weight − other active subs`; an
  active guiding point never exceeds `sub weight − other active points`; a parent weight cannot drop below what its
  active children already use. 422 `criteria.weight_exceeds_remaining` (`meta.remaining_weight`) /
  `criteria.weight_below_allocated` (`meta.allocated_weight`). Inactive items do not use the budget; activating them
  is checked again.
- **Equal distribution:** `distribute_equally` on a main (or sub) splits its weight over the ACTIVE children in
  hundredths, the remainder going to the first items by order (`400 / 3 → 133.34, 133.33, 133.33`); it is re-applied
  on every change of the parent weight or of the children set. A share of zero is refused
  (`criteria.weight_too_small_to_distribute`).
- **Evaluation type:** new sub-criteria default to `guiding_points`. The type changes only through
  `PATCH /criteria/subs/{sub}/evaluation-type`; switching to `direct` while points exist answers 422
  `criteria.points_confirm_required` with `meta.impact.point_count` until `confirm_delete_points: true` is sent.
  Guiding points cannot be added to a `direct` sub (422 `criteria.sub_evaluated_directly`).
- **Publish validation:** at least one active main; for every active main Σ active subs = main weight; every active
  `guiding_points` sub has at least one active point and Σ active points ≤ sub weight. Otherwise 422
  `criteria.weights_inconsistent` with `errors["nodes.<main|sub>.<id>"]` (translated) and `meta.problems[]`
  (`node_type, node_id, code, problem, expected_weight, actual_weight`). The same report is available from
  `GET /criteria/versions/{version}/check`; every draft edit stores it and raises STD-07 when the draft BECOMES
  inconsistent.
- **Codes** (`M1`, `M1.2`, `M1.2.3`) are stable across versions (clones keep them) and never reused, so the
  Assessments domain can match items between versions by code.
- **Published versions are frozen:** structural changes are refused with 409 `criteria.version_not_draft` and by the
  triggers `*_structure_guard`; only translation columns change, and only inside the text-correction action
  (`moashir.criteria_text_correction` session flag).

### Criteria endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/criteria/versions` (`filter[status]`, pagination) — each item adds `bound_assessments` and `bound_open_assessments` | `criteria.view` |
| POST | `/criteria/versions` `{ clone_from_version_id?, label? }` → draft (clone of the published version by default) | `criteria.manage`, `throttle:criteria-write`; 409 `criteria.draft_exists` |
| GET | `/criteria/versions/{version}` (with the same two counts), `/criteria/versions/{version}/check` | `criteria.view` |
| DELETE | `/criteria/versions/{version}` (discard a draft, soft delete) | `criteria.manage`; 409 when not a draft |
| POST | `/criteria/versions/{version}/publish` `{ confirm: true, scope?, reason? }` | `criteria.publish`, `throttle:sensitive` |
| PATCH | `/criteria/versions/{version}/labels` `{ label?, nodes[{ type, id, name?, description?, assessor_note_label? }], reason? }` | `criteria.manage`, `throttle:sensitive` |
| GET / POST | `/criteria/mains` (`version`, `search`, `filter[active]`, `sort`) | `criteria.view` / `criteria.manage` |
| POST | `/criteria/mains/reorder` `{ ordered_ids }` (complete permutation, else 422 `order.incomplete`) | `criteria.manage` |
| GET / PUT / DELETE | `/criteria/mains/{main}`; PATCH `/criteria/mains/{main}/active`; POST `/criteria/mains/{main}/subs/reorder` | `criteria.view` / `criteria.manage` |
| POST | `/criteria/subs` `{ main_criterion_id, … }` | `criteria.manage` |
| GET / PUT / DELETE | `/criteria/subs/{sub}`; PATCH `/active`, `/evaluation-type`; POST `/criteria/subs/{sub}/points`, `/points/reorder` | `criteria.view` / `criteria.manage` |
| PUT / DELETE | `/criteria/points/{point}`; PATCH `/criteria/points/{point}/active` | `criteria.manage` |
| GET | `/assessments/criteria` (`?version=` only for `criteria.view` holders, published or archived) | `assessment.criteria.view` |
| GET | `/content/model-criteria` (homepage excellence model: `id, code, order, group, name, description`, no weights) | public, `throttle:public` |

Bound-assessment counts (list and record): `bound_assessments` = every assessment bound to the version, any status,
frozen requests included (closed results keep counting on their archived version); `bound_open_assessments` = open
assessments of operational entities, i.e. what a publication replacing this version would re-bind now (the same count as
`affected_open_count`). A page is counted with two grouped queries whatever its size. They come from the
`App\Contracts\Criteria\BoundAssessmentCounter` contract (bound to `Services\Assessments\BoundAssessmentCounts`).
`bound_cycles` joins with the Award domain.

All write routes use `throttle:criteria-write` (60/min per user) unless stated. Every change is audited
(`criteria_version.*`, `criteria.*` events with before/after). The published tree is cached forever under
`criteria:published-tree` and forgotten on every publication and text correction (no cache tags, so it works with the
`database` cache driver too).

Notifications (in-app, recipients = active holders of the role, never the actor): STD-01 new main and STD-02 main
weight changed → Criteria Officer + System Administrator; STD-03 new sub → Criteria Officer; STD-04 / STD-05
evaluation type switched → Criteria Officer + System Administrator; STD-06 version published with a different scale →
System Administrator + Criteria Officer; STD-07 draft became inconsistent → Criteria Officer + System Administrator;
STD-08 published criteria edited (scope or text correction) → Criteria Officer + System Administrator.

### Editing published criteria — scope contract (Q-78, P-03)

Every edit of PUBLISHED criteria takes one of two forms:

1. **Text correction in place** — `PATCH /criteria/versions/{version}/labels` on a published or archived version:
   names, descriptions, assessor-note prompts, attachment names and the version label in ar/en. Scope is always
   `all`; it is the only edit that reaches CLOSED / certified / announced assessments. Writes a
   `criteria_edit_events` row (`kind = text_correction`, `scope = all`, `to_version_id = null`).
2. **Structural edit** — `POST /criteria/versions` (clone) → edit the draft → `POST …/publish` with a MANDATORY
   `scope` (422 `criteria.scope_required`) whenever a published version is replaced. Writes a `criteria_edit_events`
   row (`kind = structural`, `from_version_id`, `to_version_id`, `changes[]`, `affected_open_count`, `reason`).

The Super Admin must give a `reason` (422 `reason_required`) and triggers the SEC-04 alert; for the Criteria Officer
the reason is optional. `criteria_edit_events` is append-only (model guard, trigger, DB grants).

After commit the domain event `App\Events\Criteria\CriteriaEditApplied` is dispatched with: `editEvent`
(kind, scope, from/to version ids, reason, actor), `changes[]` (`CriteriaChange`: `node_type`, `code`,
`change = added | removed | modified | text_corrected`, `from_node_id`, `to_node_id`, `fields`, `before`, `after`,
`score_affecting`), `effect` (`CriteriaEditEffect`) and `rescoredCodes()` (codes whose scores can no longer be kept:
added, removed, or a changed `weight`, `evaluation_type` or `active`). What the Assessments domain does in its
queued listener `Listeners\Assessments\RebindOpenAssessmentsAfterCriteriaEdit` (open self drafts since build step 4 and
open accredited requests since build step 6; the advanced path joins it later):

| Scope | Open (non-terminal) assessments bound to `from_version_id` | Requests not yet submitted | Closed / certified / announced |
|---|---|---|---|
| `all` | re-bind to `to_version_id`; keep the scores of items whose code is NOT in `rescoredCodes()`; the others become unscored and are re-assessed | follow the new version | never re-bound |
| `new_only` | stay on `from_version_id` | stay on their version | never re-bound |
| `reset_open` | re-bind; supersede ALL scores (kept as history); return to the assessor; send STD-08 (I+E) to the affected entities and assessors | follow the new version | never re-bound |
| text correction | nothing to re-bind; texts already changed in place | — | texts corrected in place |

`affected_open_count` comes from the `App\Contracts\Criteria\OpenAssessmentCounter` contract, bound in
`AssessmentsServiceProvider` to `Services\Assessments\BoundAssessmentCounts` (open assessments bound to the
replaced version, frozen requests excluded: they are moved when their entity is restored, see "Frozen drafts on
restore").

**Scale:** each version stores `scale_kind` (`of_10 | relative`) copied from `ASSESSMENT_SCALE_KIND` (later the
Settings domain) at publication; STD-06 fires when it differs from the previous version. **Award cycles (Q-81):** a
cycle must copy the scale it starts with into `award_cycles.scale_kind` and every request of that cycle keeps that
scale, even if its requests are re-bound to a newer criteria version; a scale change applies from the next cycle.

**Award cycles and criteria publications (Session 19):** advanced requests stay on the criteria version of their cycle (text
corrections still reach them). `GET /criteria/versions/{version}/check` (the publication preview) and the publish answer add
`meta.award_cycles { running { id, name{ar,en}, status, criteria_version_id, open_requests } | null,
requests_stay_on_cycle_version: true, drafts[{ id, name, status, criteria_version_id, open_requests }] }`. The publish request
accepts `draft_cycles: keep | move` (default `keep`): `move` binds every DRAFT cycle to the new version
(`cycle.criteria_version_moved` per cycle, `meta.award_cycles.moved_cycle_ids`); the running cycle is never moved. The criteria
domain reaches the cycles through `App\Contracts\Criteria\CycleCriteriaBindings` (bound to
`Services\Award\CycleCriteriaVersionBindings`).

### Criteria seed (Q-33)

`database/data/criteria/version-1.json` holds the criteria content available today: the nine main criteria of the
homepage excellence model (names, groups enablers / creativity and learning / results, weights, descriptions) with the
sample sub-criteria and guiding points of the criteria settings screen, rebalanced so that version 1 is consistent
(total weight 8200, 19 sub-criteria, 14 guiding points). It is installed and published as version 1 by
`InstallCriteriaSeed` — through `CriteriaSeeder` (`db:seed`, local and testing) and, where seeding is refused, by:

```
php artisan moashir:criteria:install-seed     # idempotent: skipped as soon as any criteria version exists
```

## Entities (build step 3)

### Data
- `regions` (13 administrative regions, fixed codes) and `cities` (per region, maintained by holders of
  `entities.manage`; deactivated instead of deleted). A composite foreign key `(city_id, region_id)` guarantees that
  an entity's city belongs to its region.
- `entities`: bilingual `name` (Arabic required, English optional), `entity_type` (`civil_association |
  civil_foundation | waqf`), `development_sector`, `org_size`, `founding_year`, `region_id`, `city_id`,
  `governance_grade` (NCNP percentage `numeric(5,2)`, 0–100, sent as a decimal string `"87.45"`), `employees_count`,
  `annual_beneficiaries_count`, `has_strategic_plan`, `status` (`pending_verification | active | deactivated`),
  consent and lifecycle timestamps, soft delete (`deleted_at` = "Deleted entities"), `anonymized_at` (erased tombstone),
  and since Task-47 the registry details `license_number`, `founded_on`, `website_url` and the logo reference
  `logo_file_id` (see "Registry details and logo"). Every value is guarded by a CHECK constraint. The human reference is
  `ENT-` + the zero-padded id (`ENT-000014`).
- One login per entity: the single `users` row with `users.entity_id` (unique, now a foreign key). The authorized
  person's name, the official e-mail and the mobile number live on that user only (single source of truth).
- `entity_profile_changes`: one row per changed profile field (old and new value, actor). Append-only: the model
  refuses updates and deletes, a trigger rejects UPDATE and TRUNCATE, and DELETE is accepted only inside the
  permanent-deletion transaction (`moashir.entity_erasure` session flag). Only the business fields of
  `EntityProfileField` are recorded (name, classification, founding year, region, city, governance grade, counts,
  strategic plan, licence number, founding date, website); the authorized person, the official e-mail, the mobile
  number and the logo are never part of the history (logo changes are audited instead).
- `privacy_requests`: PDPL data-subject requests (`access | correction | deletion`, `open → fulfilled | rejected`).
- `email_change_requests` (Identity table) now also carries entity requests (`entity_id`).

### Registration and sign-in
`POST /auth/register` creates the entity (`pending_verification`, with its mandatory `unified_number` — format and
duplicate answer 422 on `unified_number`, see "Registry details and logo") and its owner (role `entity`, password through
`PasswordPolicy` with the local breached list), records the acceptance of every CURRENT terms version (`terms` and,
when published, `privacy`), records the explicit marketing opt-in (unticked by default) and sends a sign-in OTP to the
official e-mail. The response is always `201 { step: otp_required, requires_otp, otp{challenge_id, …}, message }`;
an e-mail that is already used gets the same answer with a decoy challenge and nothing is created or sent
(anti-enumeration). Registration answers 503 `registration.terms_unavailable` while no terms version is published.

Verifying that OTP through the existing `POST /auth/otp/verify` activates the entity (REG-03 when the profile is
incomplete) and signs it in. A pending entity that signs in with its password, or asks for a sign-in code, receives a
new registration OTP by e-mail (`step: otp_required`). A verified entity then signs in with its e-mail, verified mobile
or unified national number plus the password, or with a one-time sign-in code (see "Sign-in, sign-in codes and
lockout"). A deactivated entity gets 403 `account_deactivated`; a deleted entity gets the generic
`credentials.invalid`; neither receives OTP codes. Identity reaches the Entities domain only through the
`App\Contracts\Identity\EntityAccounts` contract (standing, verification, session summary). `GET /auth/session` now
returns `entity: { id, reference, name{ar,en}, status, profile_completed }` for entity users. Unverified registrations
are never deleted automatically; holders of `entities.verification.resend` send a reminder (REG-07, 15-minute
cooldown, 429 `entity.verification_cooldown`).

### Record scope of `entities.view`
`EntityRecordScope` applies the approved matrix: System Administrator and Requests Follower see every entity (full
staff view), the Finance Officer sees name and contact only, the Super Admin sees everything. Every other holder of
`entities.view` (assessor, filtering officer, award secretary, custom grants) sees no entity here until the
Assessments and Award domains add their assignment and cycle scopes; their `GET /entities/{id}` answers 404.

### Profile change history
`GET /entities/me/profile-changes` (the entity's own history, `entity.profile.view`) and
`GET /entities/{entity}/profile-changes` (staff) list `entity_profile_changes` newest first (`created_at`, then `id`),
paginated (`page`, `per_page` 1..100, default 15, `meta.pagination`). The staff endpoint follows `EntityPolicy::view`
(a deleted entity only for holders of `entities.delete`, 404 outside the record scope) and also needs the FULL entity
view: the Finance Officer (name and contact view only) gets 403. Item:

```
{ "id": 7, "field": "region_id", "field_label": { "ar": "المنطقة", "en": "Region" },
  "old_value": { "id": 1, "code": "riyadh", "name": { "ar": "منطقة الرياض", "en": "Riyadh Region" } },
  "new_value": { "id": 4, "code": "makkah", "name": { "ar": "منطقة مكة المكرمة", "en": "Makkah Region" } },
  "changed_by": "entity", "changed_at": "2026-11-01T10:00:00+00:00" }
```

`field` is the canonical `EntityProfileField` value (the PUT body key), `field_label` comes from
`lang/{ar,en}/entities.php` (`profile_fields`). Values are the recorded ones: `{ar,en}` for the name, the canonical value
for enums (labels in the SPA, Q-102), decimal strings for the governance grade, integers and booleans; region and city
values are resolved to `{ id, code, name{ar,en} }` with two queries per page (`ProfileChangePlacesLoader`). `changed_by`
is `entity` (the entity's own account), `staff` (an edit on behalf) or `system` (no actor); no user id, e-mail or reason is
returned. The staff endpoint adds `changed_by_staff: { name, roles: [{ key, label{ar,en} }] }` when `changed_by = staff`
(`null` otherwise; current roles of the author, loaded with one query per page); an author who no longer exists or is a
Super Admin shows `name: null` and `roles: []`. The entity's own endpoint never carries `changed_by_staff` (operator,
session 12).

### Registry details and logo (Task-47, operator decision Q-133)
Data dictionary (all on `entities`, migration `2026_11_04_120100`; no personal data — PDPL does not apply to these
organisation attributes; retention = the entity's lifetime, cleared by the permanent deletion):

| Column | Type | Rule | NDMO class |
|---|---|---|---|
| `unified_number` | `varchar(10)` null (Task-51) | «الرقم الوطني الموحد»: exactly 10 Western digits starting with 7 (`Support\ValueObjects\UnifiedNumber`); CHECK `^7[0-9]{9}$`; UNIQUE across all entities (soft-deleted included) through `entities_unified_number_unique … WHERE unified_number IS NOT NULL`. MANDATORY: required by `POST /auth/register`; editable in `PUT /entities/me` and the staff `PUT /entities/{entity}` with the same "absent = unchanged" rule, but never clearable (`null` / `""` → 422); a REQUIRED `profile_completion` item, so entities registered before Task-51 are nudged to add it; accepted as a sign-in identifier (resolves to the owner account); cleared by the permanent deletion | P (public registry data of the organisation; not personal data) |
| `license_number` | `varchar(20)` null | Western digits only, 3–20 characters (leading zeros kept, e.g. `000123`); CHECK `^[0-9]{3,20}$`; UNIQUE across all entities (soft-deleted included) through the partial index `entities_license_number_unique … WHERE license_number IS NOT NULL` | P (public registry data) |
| `founded_on` | `date` null | 1900-01-01 … today in Asia/Riyadh; CHECK `>= 1900-01-01` AND `EXTRACT(YEAR FROM founded_on) = founding_year` | P |
| `website_url` | `varchar(255)` null | `https://` only, normalised by `Support\ValueObjects\WebsiteUrl` (lower-case scheme and host, fragment dropped, bare `/` dropped, default port 443 dropped; no credentials, no other port, no IP or single-label host, no spaces or markup); CHECK `^https://`; never printed as a link in any PDF | P |
| `logo_file_id` | `bigint` null → `stored_files.id` (restrict) | current logo; the file is purpose `entity_logo`, private disk, NDMO class `I` (internal) until it is printed on a certificate, which publishes it | I → P on a certificate |

- **Founding year.** `founding_year` stays (compatibility, eligibility rules, filters). Whenever the entity has a founding
  date — sent in the request or already stored — the server sets `founding_year` to its year; the year sent by the
  client is then ignored. Clearing the date (`"founded_on": null`) lets the sent year apply again. One place:
  `Entity::profileAttributesFrom()`, used by both profile writers and the registration.
- **`PUT /entities/me` and `PUT /entities/{entity}` (staff) — absent = unchanged.** Both stay full replaces for every
  other field, but for these three fields only: a key that is ABSENT from the body leaves the stored value untouched (so
  an older client that does not know them never erases them); a key sent as `null` or `""` clears it; a value replaces
  it. 422 `validation_failed` with `errors.license_number` / `errors.founded_on` / `errors.website_url`; a licence number
  already registered for another entity answers the friendly `validation.custom.license_number.unique` message (also
  when two requests race: the unique index violation is turned into the same 422). The registration form is unchanged
  and ignores the three keys.
- **Profile completion (B-04).** Required: `basic_data`, `account_owner`, `governance_grade`, `employees_count`,
  `annual_beneficiaries_count`, `license_number`, `founded_on`; optional: `phone`, `website_url`, `logo`. `percent` =
  floor(complete required / 7 × 100) (0, 14, 28, 42, 57, 71, 85, 100). `Entity::hasCompleteProfile()` stays the single
  rule (`profile_completed`, `profile_completed_at`, Action Center `complete_profile`, REG-03 at verification). Existing
  entities without a licence number or a founding date are incomplete, and every new registration is incomplete until
  the profile adds them (REG-03 is sent at verification).
- **Logo.** `POST /entities/me/logo` (multipart `file`) and `DELETE /entities/me/logo`. PNG or JPEG only (no SVG),
  ≤ 2 MB, every side 200–2000 px (`moashir.entities.logo.*`): the Form Request checks the extension, the detected type,
  the size and the dimensions; `Services\Entities\EntityLogoIntake` checks the magic bytes and dimensions again
  (`Support\Files\RasterImage`), stores and scans the ORIGINAL through `FileIntake` (ClamAV; infected → 422
  `file.infected`, removed, `file.infected_upload_refused` + INT-05; scanner down → 422 `file.scan_failed`), then
  re-encodes the pixels with the PHP GD extension (`Services\Files\ImageReencoder`: no EXIF, text chunks, comments or
  trailing payload survive; alpha kept), stores only the re-encoded file (`<ENT-reference>-logo.png|jpg`, random path
  `files/entity-logo/YYYY/MM/<uuid>.<ext>`, attached to the entity) and removes the original. Pixels GD cannot decode →
  422 `file.content_mismatch`. The swap runs under a row lock on the entity; the previous logo is removed after commit
  unless an issued certificate keeps it (`Contracts\Entities\EntityLogoRetention`, implemented by
  `Services\Certificates\CertificateLogoRetention`). `DELETE` is idempotent (200 with `logo: null`; only a real removal
  is audited). Audits `entity.logo_uploaded` (`{ logo_file_id }` → `{ logo_file_id, mime_type, size_bytes, sha256 }`,
  public ids only, never the content or the client file name) and `entity.logo_removed`. Policy: `can:updateOwn`
  (`entity.profile.edit`), active entity only (409 `state.transition_illegal`); upload `throttle:uploads`, delete
  `throttle:sensitive`.
- **Reading the logo.** `OwnEntityResource` and `StaffEntityResource` expose `logo: { id, url, uploaded_at } | null`;
  `url` is the usual `GET /files/{file}/download` link (signed, 5 minutes, bound to the viewer; fine as an `<img src>`),
  served to the owner (`entity.profile.view`) and to staff allowed to view the entity (`EntityPolicy::viewLogo`), and
  only for the CURRENT logo (another entity → 404). The Finance Officer's contact view has no logo. Logo downloads are
  class I and are not audited one by one.
- **Permanent deletion.** `EntityLogoEraser` (erasure step) clears `logo_file_id` and detaches every logo file that no
  certificate keeps (the orphan purge removes it); the eraser also clears the three registry details.
- Demo entities (`moashir:demo:install`) carry fictional licence numbers `880000101`–`880000103`, founding dates and
  `example.sa` websites.

### Official e-mail change (Q-103)
`POST /entities/me/email/change { new_email }` sends an OTP to the NEW address. `POST /entities/me/email/confirm
{ challenge_id, code }` then follows the policy snapshot taken at request time: `requires_approval` → status
`awaiting_approval`, REG-08 to the holders of `entities.email_change.approve` and an alert to the OLD address;
`immediate_notify` → applied at once, REG-08 to the System Administrators. Approval or rejection (reason) happens in
`/admin/entity-email-changes`; REG-09 informs the old and the new address. Only one request can wait for approval;
a request still waiting for its code is replaced by a new one. Addresses are masked in the audit log.

### Deletion, frozen requests and permanent deletion (Q-85, P-01, P-02)
- `GET /entities/{entity}/deletion-impact` lists the open requests and the record categories kept by a permanent
  deletion. `DELETE /entities/{entity} { reason, confirm_open_requests, acknowledged_request_numbers[] }` is always
  allowed; with open requests it answers 409 `entity.open_requests_confirmation_required` (with `meta.open_requests`)
  until the confirmation lists exactly the current open request numbers. Sessions end, REG-10 informs the System
  Administrators and the entity, and the audit row stores `frozen_request_numbers`. `POST /entities/{entity}/restore
  { reason }` restores the previous status and stores `unfrozen_request_numbers`; every open draft then moves to the
  published criteria version per the strictest scope of the publications that replaced its version, and cannot be
  submitted until that queued update has run (see "Frozen drafts on restore").
- **Frozen guard (for the next domains):** a request is frozen while its entity is soft-deleted (derived, no column).
  Every transition or write Action of the Assessments, Award and Kit domains MUST call
  `App\Services\Entities\EntityFreezeGuard::assertEntityNotFrozen($entityId)` (409 `entity.deleted`) before its Policy or
  state-machine checks, and every work-queue query MUST call `EntityFreezeGuard::excludeFrozen($query)`. The guard
  protects the entity actions themselves (edit on behalf, deactivate, reactivate, verification resend, e-mail approval)
  and every assessment write (start, save draft, submit); the criteria re-binding, the staff assessment list and the
  requests search skip frozen requests.
- **Frozen timers (Q-105, pause and resume):** each domain with deadlines implements
  `App\Contracts\Entities\FreezableDeadlineSource` (`runningDeadlinesOf(Entity): list<FreezableDeadline>` = model +
  attribute + due date) and tags it with `EntityDeadlineFreezer::TAG` (`entities.freezable-deadline-sources`). The soft
  delete stores every running deadline with its remaining seconds in `frozen_deadlines` (same transaction, audit
  `paused_deadlines`); the restore sets each attribute to `restore time + remaining seconds` (an overdue deadline stays
  overdue by the same time) and removes the rows (audit `resumed_deadlines`); permanent deletion removes them.
  `Services\Assessments\AssessmentDeadlines` (open assessments with a `due_at`: the accredited review due date) and
  `Services\Assessments\CompletionDeadlines` (the deadline of an open accredited or advanced completion request) are the
  sources; the self path has no deadline, the advanced initial-assessment due date is an `assessments.due_at` too.
- **Open requests (extension point):** each domain implements `App\Contracts\Entities\OpenRequestSource`
  (`openRequestsOf(Entity): list<OpenEntityRequest>` with a unique `number` such as `ACC-2026-000014`) and tags it with
  `OpenRequestDirectory::TAG` (`entities.open-request-sources`). `Services\Assessments\OpenAssessmentRequests` reports
  every open assessment (the open self draft, path `self`, number `SLF-YYYY-######`, the open accredited request, path
  `accredited`, number `ACC-YYYY-######`, and the open advanced requests, path `advanced`, number `ADV-YYYY-######`, with their
  `cycle_id`). The directory stamps
  `is_frozen` on every listed request (the entity is soft-deleted), so the deletion impact of a deleted entity shows its
  frozen requests as such; sources do not set it themselves.
- **Restore steps (extension point):** each domain that must react to a restore INSIDE its transaction implements
  `App\Contracts\Entities\EntityRestoreStep` (`onRestore(Entity): void`) and tags it with `EntityRestoreSteps::TAG`
  (`entities.restore-steps`); `RestoreEntity` runs them after the deadlines resume. `Services\Assessments\CriteriaUpdateHold`
  is the first step (it holds the open drafts until their criteria update has run). Work that may run later listens to
  `EntityRestored` (dispatched after commit) instead.
- **Permanent deletion** (`DELETE /entities/{entity}/permanent { reason ≥ 20, confirmation: "<reference>",
  disclaimer_acknowledged: true }`): Super Admin only (the policy returns false for everyone else), only for a
  soft-deleted entity (409 `entity.not_soft_deleted`), 422 `confirmation.mismatch` / `disclaimer.required`. In one
  transaction `EntityEraser` runs every tagged `App\Contracts\Entities\EntityErasureStep` (`EntityEraser::TAG`,
  `entities.erasure-steps`) BEFORE anonymization, so Finance can snapshot the legal name onto its payments and
  receipts, Assessments can anonymize results and delete the frozen open requests, Certificates can keep or revoke the
  verification records, and Files can queue the file purge (listen to `EntityPermanentlyDeleted`). It then deletes the
  e-mail change requests, profile changes, privacy requests, sessions, OTP codes, password history, in-app
  notifications and reset tokens, anonymizes the owner (pseudonym, `@erased.invalid`, no password, no roles, soft
  deleted, without writing the old values to the audit log) and turns the entity into a tombstone (pseudonymous name,
  consent and reasons cleared, classification kept for aggregates). Terms acceptances and authentication events stay
  (append-only evidence). The audit event `security.entity.permanently_deleted` (reason, reference, kept list) and the
  SEC-04 alert follow. The response lists `kept[]`.

### Entities endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/regions` (active regions with active cities) | public, `throttle:public` |
| GET | `/terms/current` (`{ terms, privacy }`) | public, `throttle:public` |
| POST | `/auth/register` | public, `throttle:register` (5/hour per IP) |
| GET / PUT | `/entities/me` | `entity.profile.view` / `entity.profile.edit` (own entity), PUT `throttle:sensitive`; both answer `profile_completion { percent, items[{ key, is_complete, is_required }] }` (Task-46) and `license_number`, `founded_on`, `website_url`, `logo { id, url, uploaded_at } \| null` (Task-47); PUT: the three registry keys are "absent = unchanged" |
| POST / DELETE | `/entities/me/logo` (POST multipart `file`) | `entity.profile.edit` (own, active entity); POST `throttle:uploads` → 201, DELETE `throttle:sensitive` → 200; both answer the own entity |
| POST | `/entities/me/email/change`, `/entities/me/email/confirm` | `entity.profile.edit`; `throttle:email-change` (3/10 min) / `throttle:otp-verify` |
| GET | `/entities/me/profile-changes` (`page`, `per_page`) | `entity.profile.view` (own entity) |
| GET | `/entities` (`search`, `filter[status|region_id|city_id|entity_type|development_sector|org_size|level|trashed]`, `sort` incl. ±`level`, pagination, `meta.stats`; rows carry `level` + `level_certificate_number`, see "Excellence level on staff lists") | `entities.view` (+ `entities.delete` for `filter[trashed]=only`) |
| POST | `/entities/export` (same filters incl. `filter[level]`, CSV stream, audited with the filters, max 10,000 rows) | `entities.export` + full scope, `throttle:exports` (5/min) |
| GET / PUT | `/entities/{entity}` (PUT = core data on behalf, `reason` required; the three registry keys are "absent = unchanged"; the full staff view adds `license_number`, `founded_on`, `website_url`, `logo`) | `entities.view` / `entities.manage` |
| GET | `/entities/{entity}/profile-changes` (`page`, `per_page`) | `entities.view` + full record scope (`EntityPolicy::viewProfileChanges`) |
| POST | `/entities/{entity}/deactivate`, `/reactivate` (`reason`) | `entities.deactivate` / `entities.reactivate` |
| POST | `/entities/{entity}/verification/resend` | `entities.verification.resend` |
| GET | `/entities/{entity}/deletion-impact` | `entities.delete` |
| DELETE / POST | `/entities/{entity}`, `/entities/{entity}/restore` | `entities.delete` |
| DELETE | `/entities/{entity}/permanent` | Super Admin only |
| POST | `/entities/{entity}/export` (`reason`; JSON personal-data package, audited) | `privacy.requests.manage`, `throttle:exports` |
| GET | `/admin/entity-email-changes` (`filter[status]`, default `awaiting_approval`) | `entities.email_change.approve` |
| POST | `/admin/entity-email-changes/{id}/approve`, `/reject` (`reason`) | `entities.email_change.approve` |
| GET / POST | `/privacy/requests`; GET `/privacy/requests/{id}`; POST `/privacy/requests/{id}/fulfil` (`reason?`), `/reject` (`reason`) | `privacy.requests.manage` |
| GET | `/privacy/entities` (`search?` ≤ 100, `limit` 1..20, default 10) — entity picker of the data-subject request form: `[{ id, reference, name{ar,en}, is_deleted }]`, `meta.limit`; normalized Arabic or English name, official e-mail or reference (`ENT-000014`, `ent000014`); deleted entities included (`is_deleted: true`), erased ones never; ordered by Arabic name | `privacy.requests.manage` (no `entities.view` needed), `throttle:search` (60/min per user) |
| GET | `/cities` (admin list, inactive included) | `entities.manage` |
| POST / PUT / PATCH | `/regions/{region}/cities`, `/cities/{city}`, `/cities/{city}/active` | `entities.manage` |

State changes use `throttle:sensitive` (20/min) unless stated. Illegal transitions answer 409
`state.transition_illegal`. Notifications REG-01 … REG-10 are queued after commit (in-app + e-mail as listed in
03 §14.3, never to the actor); REG-02 is the existing OTP mail.

### Regions seed and terms publication
`database/data/regions/saudi-regions.json` holds the 13 regions (ISO 3166-2:SA order) with their main cities.

```
php artisan moashir:regions:install-seed     # idempotent: adds missing regions and cities, never overwrites edits
php artisan moashir:terms:publish terms 2026-11 --ar=/path/terms-ar.md --en=/path/terms-en.md --publisher=<staff e-mail> [--effective-from=Y-m-d]
php artisan moashir:terms:publish privacy 2026-11 --ar=… --en=… --publisher=…
php artisan moashir:terms:publish privacy 2026-11-draft --publisher=<staff e-mail> [--effective-from=Y-m-d]
```

With `--ar` and `--en` the command creates a new version from the client's files (a leading `# ` line becomes the title,
the rest is the rich text of "Legal texts and pages"; files with HTML markup are refused) and makes it current. Without
both options it publishes the stored DRAFT with that label (created in the back office or by
`moashir:legal:install-drafts`). Either way the previous current version is kept as `superseded` and the publication is
audited (`terms.published`). Authoring the texts in the back office: section "Legal texts and pages (Task-49)".

## Assessments (build step 4: shared engine + self assessment)

### Data
- `assessments`: one row per request; `request_number` (`SLF-YYYY-######`, sequential per path and Gregorian year in
  Asia/Riyadh, from `yearly_number_sequences`), `entity_id`, `path` (`self` today), per-path `status`, `criteria_version_id`
  (published version bound at start), `scale_kind` (copied from that version and kept for the life of the request, even
  when it is re-bound), `current_result_snapshot_id`, `submitted_at`, `completed_at`, `due_at` (timer for later paths).
  CHECKs tie statuses to their path and require a snapshot for `result_available`; a partial unique index allows ONE open
  self draft per entity (Q-93).
- `assessment_status_transitions`: every status change (`from_status`, `to_status`, `verb`, actor, reason). Append-only.
- `assessment_scores`: versioned scores, one current row per item and scorer (partial unique indexes). Every change
  inserts a new row (`version + 1`) and supersedes the previous one (`superseded_at`); a cleared score is a superseded row
  without successor. The raw input (`raw_value`, scale of the assessment) is stored next to the normalized `percent`
  (Q-79); `change_reason` holds a Super Admin reason. A trigger accepts only `superseded_at` going from empty to set.
- `assessment_result_snapshots`: immutable computed results (`version`, `kind = self_result`, `total_score`
  `numeric(20,8)`, `total_weight`, `percent` `numeric(9,4)`, `breakdown` with ids, codes, weights, raw values, earned
  scores and percentages at full precision). Criteria names are NOT copied: results read them from the bound version,
  so text corrections reach closed results while scores never change (Q-78).
- History tables reject UPDATE / DELETE / TRUNCATE (model guards + triggers + `production-roles.sql`); DELETE is accepted
  only inside the permanent-deletion transaction (`moashir.entity_erasure`), where `SelfAssessmentEraser` deletes the
  entity's self assessments (P-02).

### Scoring engine (`Services\Assessments\ScoringEngine`, pure, no floats)
- Raw input: `of_10` → 0–10, `relative` → 0–100, at most 2 decimals; percent = raw × 10 or raw.
- Point → sub: a `guiding_points` sub earns `sub weight × Σ(point percent × point weight) / Σ point weights` (points may
  use less than the sub weight — they are normalized by their total); a `direct` sub earns `percent × sub weight / 100`.
  Main = Σ active subs; total = Σ active mains; percentages = earned / weight × 100. Inactive items never count.
- Integer fixed point: weights in hundredths (`Weight`), percentages in ten-thousandths (`Percent`, 4 decimals), earned
  scores in hundred-millionths (`EarnedScore`, 8 decimals), one half-up rounding at the end of each division. The API
  shows 2 decimals rounded half up (`87.445` → `87.45`).

### State machine
`AssessmentPath` maps each path to its status enum behind `App\Contracts\Assessments\AssessmentState`;
`AssessmentStateMachine` checks every move (409 `state.transition_illegal` with `errors.state` / `errors.attempted`) and
writes the history row. Self: `— → draft` (start) · `draft → draft` (save) · `draft → submitted` (submit) ·
`submitted → result_available` (computed by the system in the same transaction). A result is immutable.

### Self assessment endpoints

| Method | Path | Ability / limiter | Notes |
|---|---|---|---|
| GET | `/assessments` (`filter[path|status|entity_id|is_open|has_result]`, `sort` = ±`created_at` / `submitted_at` / `completed_at`, pagination) | `assessment.history.view` | own entity; System Administrator and Requests Follower read every assessment (frozen ones only with `filter[entity_id]`); `is_open` / `has_result` = `true | false` with the meaning of the item fields `is_open` and `result` (Task-46); `meta.counts { total, open, with_result }` under the same scope and the `path` / `entity_id` filters, ignoring `status`, `is_open`, `has_result` |
| POST | `/assessments` `{ path: "self" }` | `assessment.self.manage` (entity account), `throttle:sensitive` | 201; 409 `self.draft_exists` (`meta.assessment_id`); 404 `criteria.no_published_version`; 403 `assessment.entity_required` / `assessment.entity_not_active`; 409 `entity.deleted` |
| GET | `/assessments/{assessment}` | `assessment.history.view` + record scope (404 outside it) | `{ id, number, path, status, is_open, is_frozen, criteria_update_in_progress, criteria_version_id, scale_kind, entity, result{ total, max_total, percent }|null, timestamps, status_history[] }` (`is_frozen`: the entity is soft-deleted; `criteria_update_in_progress`: a restore hold is pending, submission refused; `status_history`: see "Status history") |
| GET | `/assessments/{assessment}/form` | same | `{ assessment, scale{ kind, maximum }, scores{ subs{ "<id>": { raw_value, percent } }, points{…} }, progress{ scored, required }, criteria, criteria_update|null }` (tree of the BOUND version; `criteria_update`: see "Criteria update summary") |
| PUT | `/assessments/{assessment}` `{ scores: { subs: { "<sub id>": "7.5" }, points: { "<point id>": "8" } }, reason? }` | `assessment.self.manage`, own draft, `throttle:assessment-write` (60/min) | complete sheet: omitted or `null` items become unscored; raw values on the assessment scale; 422 field errors `scores.subs.<id>` (`score.use_points`, `score.item_not_in_version`, range); 409 when not a draft |
| POST | `/assessments/{assessment}/submit` `{ scores?, reason? }` | `assessment.self.manage`, own draft, `throttle:sensitive` | computes the result and answers with it; 422 `scores.incomplete` (`meta.missing[]`, field per item); 409 `assessment.criteria_update_in_progress` while the restore hold is pending (also for the Super Admin) |
| GET | `/assessments/{assessment}/result` | `assessment.history.view` + record scope | before the result: 200 `{ id, number, path, status, is_frozen, available: false }` (Q-94); then `{ …, available: true, total, max_total, percent, main[], sub[], points[] }` with names `{ar,en}` |

A Super Admin acting on an entity's draft must send `reason` (422 `reason_required`), which is stored on the score rows,
the history and the audit, and raises SEC-04. Staff with read scope get 403 on writes; other entities get 404.
Self results are a MEASUREMENT ONLY: nothing reads them for the Kit, lenses or certificates (Q-14).

Notifications to the entity owner (queued, after commit): SELF-01 start (I), SELF-02 draft saved (I, at most once per
60 minutes per assessment — `moashir.assessment.draft_notice_cooldown_minutes`), SELF-03 submitted (I+E), SELF-04
result available (I), STD-08 (I+E) when a `reset_open` criteria edit clears a draft; INT-05 (I) to the System
Administrators when the criteria update after a restore fails for good. Audit events: `assessment.created`,
`assessment.scores_saved` (old/new raw value per item code), `assessment.submitted`, `assessment.result_computed`,
`assessment.rebound`.

### Status history
`GET /assessments/{assessment}` adds `status_history`: every `assessment_status_transitions` row of the request, oldest
first (by id), `[{ from, to, verb, at, actor }]`. `from` is `null` for the start; `verb` is the canonical
`AssessmentVerb` (`start`, `submit`, `compute_result`; `save_draft` is never written for the self path); `at` is ISO
8601 UTC; `actor` is `entity` (the entity's own account), `staff` (any other user, the Super Admin acting on behalf
included) or `system` (no actor, e.g. the computed result). The stored reason is never returned. Staff readers (System
Administrator, Requests Follower, Super Admin) also get `actor_staff: { name, roles: [{ key, label{ar,en} }] } | null`
(`null` unless `actor = staff`; a deleted user or a Super Admin shows `name: null, roles: []`), loaded with one query for
the whole history (`Services\Identity\StaffActorReader`, shared with the profile change history). The entity's own view
never carries `actor_staff`. Same policy and record scope as the record (403 without `assessment.history.view`, 404
outside the scope). The list, the form and the PUT draft answer keep the plain assessment shape.

### Criteria update summary
Every re-binding (criteria edit with scope `all` or `reset_open`, or the restore re-binding) inserts one
`assessment_criteria_moves` row in the same transaction: `assessment_id`, `from_version_id`, `to_version_id`, `scope`,
`triggered_by` (`criteria_edit | entity_restore`) and the item lists `kept`, `reset`, `added` (`[{ level, code }]`, in
criteria order). `kept` = items whose score was carried; `reset` = items that had a score and lost it; `added` = items to
assess that were not scorable in the previous version. Append-only (model guard, trigger, `production-roles.sql`); DELETE
only through the permanent-deletion cascade (`moashir.entity_erasure`). Audit rows are never read for it.

`GET /assessments/{assessment}/form` returns the LATEST move as `criteria_update` (`null` when the request never moved):

```
{ "from_version_id": 3, "from_version_number": 1, "to_version_id": 5, "to_version_number": 2,
  "moved_at": "2026-11-01T10:00:00+00:00", "scope": "all", "trigger": "criteria_edit",
  "kept":  [{ "level": "point", "code": "M1.2.1", "label": { "ar": "…", "en": "…" } }],
  "reset": [{ "level": "sub", "code": "M1.1", "label": { "ar": "…", "en": "…" } }],
  "added": [{ "level": "point", "code": "M1.2.3", "label": { "ar": "…", "en": "…" } }] }
```

Labels are read at request time (sub name or guiding-point description), from the version the draft follows, and for a
reset item that no longer exists there from the version it left, so text corrections reach the summary like results.
The form makes one extra query when the request never moved and at most six when it did.

### Criteria edits on open drafts
The queued listener (3 tries, idempotent: it skips drafts no longer bound to the replaced version) re-binds the open
self drafts bound to `from_version_id`, frozen ones excluded. `all`: scores of items whose code is not in
`rescoredCodes()` and that are still scorable at the same level are copied to the matching items of the new version;
the others become unscored. `reset_open`: every score is superseded and the entity receives STD-08 (I+E). `new_only` and
text corrections re-bind nothing. Results (`result_available`) are never re-bound. Each re-binding is audited
(`assessment.rebound`: `criteria_version_id`, `scope`, `kept_codes`, `cleared_count`, `trigger = criteria_edit`,
`edit_event_ids`) and recorded for the form (`assessment_criteria_moves`, see "Criteria update summary").

### Frozen drafts on restore (operator decisions, sessions 8 and 10)
A criteria edit skips the drafts of soft-deleted entities (they accept no write, P-01). When the entity is restored,
EVERY open self draft of the entity moves to the published criteria version per the strictest applicable scope,
including a draft that a `new_only` publication had left on an older version BEFORE the freeze. `EntityRestored`
reaches the queued listener `Listeners\Assessments\RebindAssessmentsAfterEntityRestore` (3 tries, backoff 30 / 120 s,
timeout 300 s), which runs `Actions\Assessments\RebindRestoredAssessments` for every open self draft of the entity:

1. **Missed publications** (`Services\Assessments\MissedCriteriaEditsReader`): the chain of structural publications that
   replaced the draft's version, then the version that replaced it, up to the currently published version (read from
   `criteria_edit_events`), whether they were recorded before or during the freeze.
2. **Scope** (`Data\Assessments\MissedCriteriaEdits`): the strictest scope of the chain, `reset_open` > `all` >
   `new_only` (`CriteriaEditScope::isStricterThan`). Examples: `new_only` then `all` → `all`; `all` then `reset_open` →
   `reset_open`; `reset_open` then `new_only` → `reset_open`; a `new_only` before the freeze then a `reset_open` during it
   → `reset_open`; only `new_only` publications (before or during the freeze) → nothing moves.
3. **Effect**, applied to the currently published version (end of the chain): `all` → re-bind and keep the scores of the
   items that NONE of the publications of the chain changed (union of their score-affecting codes), the others become
   unscored; `reset_open` → re-bind, supersede every score (kept as history) and send STD-08 (I+E) to the entity;
   `new_only` → the draft stays on its version with its scores. The scale of the assessment never changes.
4. **Safety**: each draft is handled in its own transaction that takes a shared lock on the published criteria version
   (a concurrent publication waits, so its own queued re-binding sees the moved draft) and the row lock of the
   assessment. The job is idempotent: a draft already on the published version, no longer open, or frozen again (entity
   deleted before the job ran) is skipped; the next restore handles it. Closed results are never moved.
5. **Audit**: `assessment.rebound` with `trigger = entity_restore`, `scope` (the strictest), `edit_event_ids` (every
   publication of the chain), `kept_codes`, `cleared_count`; actor empty (queued system action). The move is also
   recorded in `assessment_criteria_moves` (`triggered_by = entity_restore`) and shown on the form as `criteria_update`.

**The re-binding window is closed (session 10).** Inside the restore transaction the restore step
`Services\Assessments\CriteriaUpdateHold` sets `assessments.criteria_update_pending_since` on every open self draft of
the entity (the restore time). While it is set, `POST /assessments/{assessment}/submit` answers 409
`assessment.criteria_update_in_progress` (ar/en, also for the Super Admin) and the assessment resources show
`criteria_update_in_progress: true`; saving the draft stays allowed. The job clears the hold of each draft in the same
transaction as its re-binding (or as the check that nothing moves), so a retried or repeated job is harmless; a draft
frozen again before the job keeps its hold until the next restore. A CHECK constraint
(`assessments_criteria_update_hold_check`) makes a submitted assessment with a hold impossible. When the job still fails
after its last try, the listener's `failed()` runs `Actions\Assessments\HandleFailedRestoreRebind`: it releases every
hold of the entity (the drafts stay on their version and can be submitted), audits each release
(`assessment.criteria_update_abandoned`: `criteria_update_pending` true → false, `criteria_version_id`,
`trigger = entity_restore`) and alerts the System Administrators in-app (INT-05, with the entity name, reference and the
number of released drafts). The exception itself reaches GlitchTip through the queue worker; the failed job can be
retried with `php artisan queue:retry <id>` (it then re-binds the drafts that are still behind).

**Sweep of stale holds.** If the queued job is lost (for example a queue outage right after the restore commit), the
scheduled `php artisan moashir:assessments:sweep-criteria-update-holds` (every 10 minutes) re-dispatches
`Jobs\Assessments\SettleRestoredCriteriaUpdate` for every entity whose drafts have been held for more than 30 minutes
(`moashir.assessment.criteria_update_hold_stale_minutes`), frozen entities excluded. Each re-dispatch increments
`assessments.criteria_update_redispatches` and writes one `assessment.criteria_update_redispatched` audit on the entity
(`sweep`, `assessment_ids`); the job is unique per entity for 10 minutes and idempotent. The third sweep without release
alerts the System Administrators in-app (INT-05, "still pending"). Releasing a hold resets the counter; a job that fails for
good releases the holds like the listener (`HandleFailedRestoreRebind`).

## Accredited assessment (build step 6)

The accredited path reuses the shared engine (state machine, versioned scores, `ScoringEngine`, result snapshots, freeze
guard, criteria re-binding and the restore hold) and adds the paid opening, the entity form with CV entries and evidence,
the Requests Follower queue, the assessor review and the Super Admin overrides. The accredited certificate and the
feedback report are described in "Certificates, feedback report and PDF documents".

### Data
- `assessments` (path `accredited`, numbers `ACC-YYYY-######`): `payment_id` (the paid payment that opened the request,
  unique; a CHECK requires it exactly for the accredited path), `review_sla_days` (the SLA the due date was computed with),
  `overdue_notified_at` (ACC-11 sent once per due date), `refunded_at`. Partial unique index
  `assessments_one_open_accredited`: ONE open accredited request per entity. The CHECKs of build step 4 were rewritten per
  path (draft never submitted, completed needs a snapshot, refunded needs `refunded_at`, the restore hold only on open
  requests).
- `assessment_assignments`: assessor, assigned by, `status` (`active | reassigned | completed | ended`), `reason`,
  `started_at`, `ended_at`, `end_reason`. One active row per request (partial unique). Append-only history: only an ACTIVE
  row may change, and only its status, start and end columns (model guard + trigger); DELETE only through the
  permanent-deletion cascade.
- `assessment_cv_entries` (one text per sub-criterion), `evidence_files` (sub-criterion, optional attachment slot key,
  `stored_file_id`, `uploaded | removed`), `assessment_completion_requests` (notes, items `[{ sub_code, kind: cv|evidence,
  note }]`, `deadline_at`, `expiry_outcome` (`continue_as_submitted | decline`, chosen by the assessor, Session 18 = Q-04),
  `open | resubmitted | cancelled | expired_continued | expired_declined`, one open per request, partial index on the open
  deadlines) and `evidence_bundles` (zip of the clean evidence, `preparing | ready | failed | expired`).
- `assessment_feedback_items`: strengths / improvement points / notes per sub-criterion (Q-10), versioned like scores (a
  change supersedes the current row, a cleared field is a superseded row without successor; trigger + model guard).
- CV entries, evidence and feedback are keyed by the stable sub-criterion CODE, not by the id of one criteria version, so a
  criteria re-binding (publication with scope `all` / `reset_open`, or the restore re-binding) carries them to the new
  version without copying rows; the API always speaks the sub-criterion ids of the bound version.
- Assessor scores live in `assessment_scores` with `scorer_role = assessor` (raw input + normalized percent, Q-79). A
  reassignment keeps the scores: the new assessor continues the same sheet. Snapshots: `accredited_result` at completion,
  `override_recompute` after a Super Admin override.

### State machine (`AccreditedAssessmentStatus`)
`— → awaiting_payment → draft` (both written by the system when the payment is confirmed) · `draft → draft` (CV and
evidence edits do not change the status) · `draft → submitted` (entity) · `submitted → assigned` (assign) ·
`assigned | under_review | needs_completion → same` (reassign) · `assigned → under_review` (start review) ·
`under_review → needs_completion` (request completion) · `needs_completion → under_review` (entity resubmits, or the
completion deadline passed with the outcome `continue_as_submitted`) · `needs_completion → declined_incomplete` (the
completion deadline passed with the outcome `decline`) · `under_review → completed` (complete) · `draft … needs_completion →
refunded` (recorded refund). Completed, refunded and declined_incomplete are final; only the Super Admin moves a completed
request (reopen, force); a refunded or declined request is never forced. Illegal moves answer 409
`state.transition_illegal`. Every move writes `assessment_status_transitions` (verbs `start, open_draft, submit, assign,
reassign, start_review, request_completion, resubmit, expire_completion, complete, refund, override_score, reopen,
force_transition`; `expire_completion` has no actor, `actor = system` in the history), so `status_history` on
`GET /assessments/{id}` covers the path (staff names only for the global readers, never for the entity or an assessor).

### Opening and refund
- `PaymentPaid` (purpose `accredited`, from the verify endpoint, the webhook job or the manual settlement) reaches the queued
  listener `OpenAccreditedRequestOnPayment` (5 tries). `OpenPaidAccreditedRequest` locks the payment and the entity: an
  existing request of that payment is returned (idempotent, a duplicate event changes nothing); otherwise the request is
  created bound to the published criteria version and opened as a draft, with ACC-01 (entity, I+E) and the audits
  `assessment.created` + `assessment.opened`. If another accredited request of the entity is still open, no request is
  created: `assessment.duplicate_payment` is audited and PAY-08 asks Finance to refund. Without a published criteria
  version, INT-05 alerts the System Administrators and `moashir:assessments:open-paid-requests` (every 10 minutes) opens
  the missing requests later; the same sweep repairs a lost queue job.
- `POST /payments { purpose: accredited }` is refused while the entity has an open accredited request (409
  `accredited.request_open`, `meta.assessment_id`) or a paid accredited payment whose request is not open yet (409
  `accredited.payment_being_processed`). Finance reaches this rule only through the `App\Contracts\Finance\
  PurchaseEligibilityCheck` contract (tag `finance.purchase-eligibility-checks`), checked under the entity lock of the order.
- `PaymentRefunded` (recorded refund, Q-72) reaches `CloseAccreditedRequestOnRefund`: an open request moves to `refunded`
  (the refund reason is stored on the history row), the active assignment ends, an open completion request is cancelled,
  scores are kept, and the assessor is told in-app. A completed request keeps its result; `assessment.refund_after_completion`
  is audited. A frozen request is closed too (Q-106 allows the refund).

### Entity side
- `GET /assessments/accredited/current` → `{ awaiting_payment: { payment_id, reference, status, expires_at } | null, active:
  Assessment + progress | null, latest_closed: Assessment | null }`. The waiting-for-payment phase is the open accredited payment order.
  `active.progress` (Task-46) = `{ subs_total, subs_complete, remaining[{ sub_criterion_id, code, name{ar,en}, cv_missing,
  attachments_required, attachments_uploaded }] }` while the request accepts entity content (`draft`, `needs_completion`),
  otherwise `null`; same rules as submit (`SubmissionCoverage`), in `needs_completion` only the requested sub-criteria count;
  `remaining` ordered by code.
- `GET /assessments/{id}/form` (accredited) → `{ assessment, criteria (bound version), cv { "<sub id>": text }, evidence[],
  missing { missing_cv[], missing_attachments[] }, completion_request | null, upload_limits { max_file_mb, allowed_types[],
  max_files_per_sub }, criteria_update | null }`. No score is ever returned to the entity before completion; staff readers
  get the same form with clean evidence only.
- `PUT /assessments/{id}/cv { cv: { "<sub id>": text | null }, reason? }` — partial; `null` or an empty text clears the entry;
  the sub-criterion must be in the bound version (422 `assessment.sub_not_in_version`) and take a CV (422 `cv.not_allowed`);
  10,000 characters at most. Allowed in `draft`, and in `needs_completion` for the requested items only (422
  `completion.item_not_requested`). ACC-02 (entity, in-app, once per hour per request).
- `POST /assessments/{id}/submit` — `draft → submitted` or, from `needs_completion`, `→ under_review` (resubmission: the
  completion request becomes `resubmitted`, the assessor is told in-app; refused with 409 `completion.deadline_passed` once the
  completion deadline has passed, see "Completion deadline expiry"). Every active sub-criterion with `has_cv` needs a CV
  entry and every required attachment slot (of the sub-criterion or of its active guiding points) needs at least one
  evidence file that is clean or still being scanned; otherwise 422 `submission.incomplete` with `errors["cv.<sub id>"]`,
  `errors["attachments.<sub id>.<key>"]` and `meta.missing_cv / missing_attachments`. Submission sets `submitted_at`, the
  due date (`submitted_at + accredited review SLA`) and `review_sla_days`; ACC-03 (entity I+E, Requests Followers e-mail) and
  ACC-04 (Requests Followers in-app). 409 `assessment.criteria_update_in_progress` while the restore hold is set.
- Writes follow the write gate: frozen entity 409 `entity.deleted`, deactivated entity 403 `assessment.entity_not_active`,
  Super Admin on behalf 422 `reason_required` + SEC-04, staff with read scope 403, everyone else 404.

### Follower queue and assignment
- `GET /requests` — submitted requests of operational entities of ONE path (drafts and frozen requests never listed; separate
  queues per path, see "Path distinction"): `filter[path]` (default: the first path the caller follows, `accredited` first; a
  path outside the caller's request scope answers 422), `filter[status]` (`submitted | assigned | under_review | needs_completion | completed |
  refunded | declined_incomplete`), `filter[overdue]=true|false`, `filter[assessor_id]`, `search` (request number, entity name or e-mail),
  `sort` ±`submitted_at | due_at | status | created_at` (default `-submitted_at`), pagination, `meta.stats { total, by_status,
  overdue }` (for the served path), `meta.path` and `meta.path_counts[{ path, path_label{ar,en}, total, awaiting_assignment,
  overdue, preparing }]` (one entry per followed path, independent of the other filters, for the sidebar; `preparing` counts
  the paid drafts below and never enters `stats`, the dashboards or the SLA). Row: `{ id, number, path,
  path_label{ar,en}, status, is_frozen, entity{ id, reference, name, status, level }, assessor{ id, name } | null, assigned_at,
  completion_deadline, submitted_at, due_at, is_overdue, completed_at }`.
- `GET /requests/preparing` — the preparing stage (Task-75, DF-1): open requests of operational entities that the entity has
  not sent yet (status `draft`; for the accredited path the fee is already paid). Read only, never assignable, outside the
  assignment statistics and the SLA. `filter[path]`, `filter[cycle_id]` (advanced), `search`, `sort` ±`created_at` (default
  oldest opened first), pagination; `meta.path`, `meta.path_counts` (as above). Row: `{ id, number, path, path_label, status,
  is_frozen, entity{ id, reference, name, status, level }, award_cycle, opened_at, payment{ reference, channel, status,
  is_test, paid_at, receipt{ number, status, is_test } | null } | null, progress{ subs_complete, subs_total } | null,
  last_activity_at }` (latest of the opening, a CV edit or an evidence change). Holders of `finance.operations.view` also get
  `payment.id` and `payment.receipt.id` to open the finance record and download the receipt.
- `GET /requests/{id}` — row + `payment { paid }` (global readers also get the payment card fields of the preparing row:
  `reference, channel, status, is_test, paid_at, receipt`, plus `id` / `receipt.id` for `finance.operations.view`), `record` (the assessment with `status_history`), `assignment_history[{ id,
  assessor{ id, name }, status, assigned_at, started_at, ended_at, assigned_by{ id, name }, reason, end_reason }]`,
  `completion_requests[]`. The assigned assessor (`requests.assigned.view`) sees only their own assignment rows, without
  `assigned_by`, reasons or staff names.
- `GET /requests/assessors?assessment_id=&path=&search=` — active staff holding `assessment.score.manage` (assessor role) AND
  qualified for the path of the request (`accredited` → qualification `accredited`, `advanced` → `advanced_initial`), never a
  Super Admin, never the caller (SoD-4), never the current assessor of the given request: `[{ id, name, open_requests }]`.
  Without `assessment_id` the list follows `path` (default: the first followed path); an `assessment_id` that does not exist or
  is outside the caller's request scope answers 422 (`errors.assessment_id`).
- `POST /requests/{id}/assign { assessor_id, reason? }` (`submitted → assigned`) and `POST /requests/{id}/reassign {
  assessor_id, reason 10..1000 }` (status kept; previous assignment `reassigned` with the reason). 422 `assessor.invalid`,
  `assessor.not_qualified` (`meta.required_qualification`, `meta.path`), `sod_self_assignment`, `assessor.same`; 409 on any other
  state. ACC-05 to the assessor (I+E) and the other Requests
  Followers (I+E); on reassign the previous assessor is told in-app and loses access at once. The Super Admin must give a
  reason (SEC-04).
- SLA: due date = submission + `assessment.accredited_review_sla_days` (default 14). A request is late when its due date is
  past and it is `submitted`, `assigned` or `under_review` (not while waiting for the entity). `moashir:assessments:flag-overdue`
  (hourly) sends ACC-11 once per due date to the assessor, the Requests Followers and the System Administrators. Changing the
  SLA dispatches `RecomputeAccreditedDueDatesJob` (unique until processing, idempotent): every open request with a due date
  moves by the difference between the new SLA and its `review_sla_days`, so a time already shifted by a freeze is kept; the
  paused due date of a frozen request is shifted the same way.

### Assessor work
- `GET /requests/assigned?filter[path]=accredited|advanced&filter[tab]=new|in_progress|completed` — the caller's requests of
  ONE path (current or completed assignment), frozen ones excluded, ordered by due date: `{ id, number, path, path_label,
  status, entity{ …, region }, assigned_at, started_at, completion_deadline, submitted_at, due_at, is_overdue, completed_at }`.
  Default path: the first path of `meta.path_counts`. `meta.path` and `meta.path_counts[{ path, path_label, new, in_progress,
  completed }]` list the paths the assessor is qualified for plus any path where they hold assignments.
- `GET /assessments/{id}/review` — `{ assessment, scale, scores{ subs, points }, progress, mode: edit|read_only, submitted_at,
  due_at, is_overdue, completed_at, entity (summary + profile), criteria, cv, evidence[] (clean only), feedback{ "<sub id>": {
  strengths, improvement_points, notes } }, completion_request, context[] (latest other self / accredited result, for
  `assessment.context.view`) }`. Edit for the active assignee (and the Super Admin) while the request is open; read only for
  the Requests Follower and the System Administrator (`requests.review.read`) and for the assessor after completion.
- `POST /assessments/{id}/start-review` (`assigned → under_review`, idempotent when already started; ACC-06 entity + followers,
  in-app) · `PUT /assessments/{id}/review/scores { scores: { subs, points } }` (partial: listed items change, `null` clears;
  raw values on the request scale; versioned) · `PUT /assessments/{id}/review/feedback { feedback: { "<sub id>": { strengths?,
  improvement_points?, notes? } } }` (≤ 5,000 characters each, versioned, OPTIONAL — Session 18) · `POST
  /assessments/{id}/request-completion { notes 10..2000, items[{ sub_criterion_id, kind: cv|evidence, note? }] 1..100,
  deadline_at (future, ≤ 60 days), expiry_outcome: continue_as_submitted | decline (required) }` (`under_review →
  needs_completion`, ACC-07 entity I+E, whose text states the outcome) · `POST /assessments/{id}/complete { reason?,
  issue_certificate? (deprecated, accepted as a boolean and ignored — Task-79) }` (all items scored —
  422 `scores.incomplete`; feedback items are optional, an assessor may complete with scores only; computes the
  `accredited_result` snapshot, closes the assignment, ACC-08 entity + followers and ACC-10 entity). Scoring needs `under_review`; only the active assignee
  passes (`can:score` + a re-check under the row lock, 403 `assessment.not_assignee`). Every write: frozen 409 `entity.deleted`,
  completion blocked while the restore hold is set.

### Completion deadline expiry (Session 18 = Q-04 rule)
- The completion request object (entity form `completion_request`, assessor review `completion_request`, request detail
  `completion_requests[]`) is `{ id, status: open|resubmitted|cancelled|expired_continued|expired_declined, notes, items[{
  sub_criterion_id, code, kind, note }], deadline_at, expiry_outcome: continue_as_submitted|decline, requested_at, resolved_at
  }`.
- `moashir:assessments:expire-completion-requests` (every minute, without overlapping, action
  `ExpireCompletionRequests`, one transaction per request in `CompletionRequestExpirer`) takes every OPEN completion
  request whose `deadline_at` is in the past, of an accredited request in `needs_completion` of an operational entity and
  without a pending criteria update hold. Under the row lock it re-checks all of this and then applies the outcome stored on
  the request:
  - `continue_as_submitted` → `needs_completion → under_review` with the data the entity had at the deadline; the completion
    request becomes `expired_continued`; the assignment stays active and the assessor continues (scores kept); audit
    `assessment.completion_expired`.
  - `decline` → `needs_completion → declined_incomplete` (terminal, not open: the entity may buy a new accredited assessment);
    the completion request becomes `expired_declined`; the active assignment ends (`end_reason = declined_incomplete`); scores,
    CV and evidence are kept; audit `assessment.declined_incomplete`. NO refund is made or started: a refund follows the
    recorded-refund flow (Q-50/Q-60) and leaves the declined request as it is.
  - Both write the history row (verb `expire_completion`, no actor) and send ACC-12 (I+E) to the entity, the assessor of the
    request and the Requests Followers (the assessor is not counted twice), after commit.
- Idempotent: a second run, an overlapping run or a retried transaction finds no open expired request and changes nothing.
  A completion resubmitted before the deadline is `resubmitted` and is never touched.
- Freeze (P-01, Q-105): a frozen request is skipped; its deadline is paused (`frozen_deadlines`) and resumed on restore with
  the remaining time, so the outcome applies at the shifted deadline. A restored request waits until its criteria update
  hold is released (minutes) before the outcome applies.
- After the deadline the entity can no longer change the request: CV, evidence upload / removal and resubmission answer 409
  `completion.deadline_passed` (ar/en) until the job applied the outcome; then the new state answers 409
  `state.transition_illegal`.
- New notification code ACC-12 "completion deadline expired → continued as submitted / declined" (variants `continued` and
  `declined` for `entity`, `assessor`, `staff`). Links: entity `/assessment/accredited`, assessor `/admin/requests/{id}/review`
  (continued) or `/admin/requests/{id}` (declined), followers `/admin/requests/{id}`.

### Results
`GET /assessments/{id}/result` follows Q-94: `available: false` with the status until the request is `completed` (also after
a reopen); then the build step 4 shape plus `feedback[{ sub_id, code, name, percent, strengths, improvement_points, notes }]`.
No assessor identity is ever part of a result, the record or the form returned to the entity (Q-19).

### Super Admin overrides (Super Admin only; reason 20..1000; SEC-04; audited)
- `POST /assessments/{id}/scores/override { scores, reason }` — on a published result (accredited `completed`, self
  `result_available`): new score versions with the reason, a new `override_recompute` snapshot, `assessment.score_overridden`.
- `POST /assessments/{id}/reopen { reason }` — `completed → under_review` with a new assignment of the last assessor (or
  `submitted` when that account can no longer assess); the result is hidden again until the next completion.
- `POST /assessments/{id}/transition/force { to_status: draft|submitted|assigned|under_review|completed, reason }` — never from
  `awaiting_payment`, `refunded` or `declined_incomplete`; `draft` clears the submission, `submitted` ends the assignment, `assigned` /
  `under_review` need an active assignment (409 `assessment.assignment_required`), `completed` needs a result (409
  `assessment.result_required`).

### Assessment settings (`/api/v1/settings/assessment`, `settings.assessment.manage`)
The per-path settings (review SLA, upload limits, price, entity-facing texts) live in `/settings/assessment/paths/{path}` (see
"Path distinction"). This section keeps its shape for backward compatibility: `accredited_review_sla_days` and
`advanced_initial_review_sla_days` are the review SLA of the accredited and advanced paths; `upload_max_file_mb`,
`evidence_allowed_types` and `evidence_max_files_per_sub` are ALIASES of the accredited path's upload limits (deprecated, to be
removed once the SPA uses the per-path screen); `general_upload_max_file_mb` (new) is the shared `files.max_size_mb` used by
non-evidence uploads (refund evidence).
`GET` → `{ accredited_review_sla_days, accredited_review_sla_limits { min: 1, max: 120 }, upload_max_file_mb,
evidence_allowed_types[], evidence_max_files_per_sub, evidence_type_catalog[], general_upload_max_file_mb, advanced_min_entity_age_years,
advanced_disqualification_score, advanced_initial_review_sla_days, committee_entity_data_visibility }` (the three advanced keys are the defaults copied into a new
award cycle: 3 years, "60.00", 14 days; 0..100 years, 0..100 with two decimals, 1..120 days). `PUT` is partial (same keys; types ⊂
`pdf, docx, xlsx, pptx, jpg, png`, at least one; file size 1..100 MB; 1..50 files per sub-criterion; `reason` 10..1000,
required for the Super Admin). Each changed key writes `settings.assessment_updated`. `committee_entity_data_visibility` (`name_only` default | `code_only` |
`full`, Q-57) decides what a committee assessor sees of the entity (see "Award committee").

### Lifecycle hooks
Open accredited requests are listed in the deletion impact, frozen with the entity (no write, out of every queue and of the
overdue sweep), their review due date and completion deadline are paused and resumed (Q-105), the restore hold and the
restore re-binding cover them, and a criteria publication re-binds them by scope (`reset_open` supersedes the assessor
scores and sends STD-08 to the entity and the assessor, I+E). Permanent deletion (`ReviewedRequestEraser`) deletes the
open (frozen) requests, every CV entry, completion request, evidence row and bundle, and detaches the evidence and bundle
files so the daily orphan purge removes them; completed results stay on the erased tombstone and the payments stay.

## Periodic accredited assessment (Task-79, 03 §7.2, §14.3, §19)

The full contract (JSON shapes, codes, placeholders) is `agents/tasks/reports/Task-79-contract.md`.

### Data
- `assessment_periods`: cadence (`quarterly | semi_annual | annual`), key (`2027-Q1`, `2027-H1`, `2027`), year, sequence,
  `starts_on` / `ends_on` (inclusive, Asia/Riyadh calendar), `starts_at` / `ends_at` (UTC instants, `ends_at` exclusive),
  `is_shortened`, `notice_sent_at`, `reminders_sent` (days), `last_digest_on`, `closed_at`, `superseded_at`. One live
  period per key and per start date (partial unique indexes).
- `assessments.period_id` + `period_assigned_at` (accredited, set at `draft → submitted`, never recomputed; checked in the
  database) and `assessments.paid_draft_reminded_at` (ACC-13 window).
- `entity_period_statuses` (`assessed | in_progress | missed | closed_without_result | frozen | not_required`), one row per
  entity and ended period; `not_submitted` is live only (current period).
- `period_notices` (period, entity, code `PER-01 | PER-02 | PER-03 | PER-05`, threshold days): one row per notice sent;
  the unique key makes every notice idempotent.
- `certificates.valid_until` (date, accredited only, required by a check constraint, immutable through the certificate
  guard trigger). `certificate_verification_logs.outcome` gains `expired`.
- Settings (group `periodic`): `periodic.cadence`, `periodic.notice_days_before`, `periodic.reminder_days_before_end`,
  `periodic.audience`, `periodic.certificate_validity`, `periodic.paid_draft_reminder_days`, and the read-only
  `periodic.rollout_from` seeded by the migration with the first full quarter after the release (2027-01-01).

### Rules
- Periods are generated from the rollout through today and the next four (`moashir:periods:generate`, daily 00:05
  Riyadh, idempotent; also after a settings change and, if missing, at submission). Started periods never change. A
  cadence change takes effect at the end of the current period: the first new period is the new-cadence period that
  contains the next day, shortened to start that day; unstarted periods that no longer match are superseded and PER-05
  corrects the PER-01 already sent for them (queued listener, tries 3, backoff 60/300, timeout 300).
- Submission: the period that contains the submission instant (Asia/Riyadh); before the rollout the request has no period
  (`pre_periodic`, «قبل التقييم الدوري»). A second submission in a period that already holds a completed result returns
  409 `period.already_assessed` with `meta.next_submission_from`, `meta.current_period`, `meta.next_period`; the draft is
  kept. Requests closed without a result do not count. A late result keeps its period.
- Closing (`moashir:periods:close`, daily 00:15 Riyadh): every active verified entity (and every frozen one) verified before
  the period end gets a stored status; `not_required` when verified after `ends_on` − the largest reminder interval;
  PER-03 for `missed` when the period ended in the last 7 days; `in_progress` rows are settled again every day.
- Notices (`moashir:periods:notify`, daily 09:00): PER-01 from `starts_on − notice_days_before` until the start; PER-02 at each
  reminder threshold to entities without a submission in the period (closest threshold only when several became due;
  link to the open draft when there is one). Audience: active, verified, not deleted entities (service notices).
- Digest (`moashir:periods:digest`, daily 08:00): PER-04 in-app to Request Followers (accredited path) and System
  Administrators, at most once a day per period.
- Paid drafts (`moashir:assessments:remind-paid-drafts`, daily 10:00): ACC-13 after `periodic.paid_draft_reminder_days` without
  entity activity, at most once per window; `POST /requests/{id}/remind` (`requests.assign`) at most once per 24 hours
  (409 `request.reminder_too_soon` with `meta.next_remind_at`, 409 `request.not_paid_draft`), audited `requests.reminder_sent`.
- Certificates: issued automatically on completion while issuance is on (`issue_certificate` is accepted for older clients
  and ignored); `valid_until` = the end of the period after the request period, or the end of the first rollout period for
  pre-periodic requests (back-filled for existing certificates); a replacement keeps the replaced `valid_until`. The entity
  level is the latest issued, not revoked/suspended, unexpired accredited certificate (`level_valid_until` added to the
  entity level fields); public verification returns `expired` after `valid_until`.

### Endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/assessments/accredited/current` | as before; adds `periodic`, `submission`, `apply`, `pricing` |
| GET | `/assessments?filter[path]=accredited` | as before; rows add `row_type`, `row_key`, period, payment, level, certificate, kit; missed periods merged for one entity (`meta.missed_periods`) |
| GET | `/assessments/{assessment}` | as before; adds `available_tabs`, `default_tab` |
| GET | `/dashboard/summary?board=entity` | as before; widgets `level`, `paths`, `next_step` |
| GET / PUT | `/settings/assessment/periodic` | `settings.assessment.manage` (Super Admin with a reason), PUT `throttle:sensitive`, audited `settings.periodic_updated` |
| POST | `/settings/assessment/periodic/preview` | same ability, `throttle:sensitive`, no write |
| GET | `/assessment-periods?filter[state]=past\|current\|upcoming` | `requests.queue.view` or `requests.assigned.view` or `settings.assessment.manage` |
| GET | `/requests?filter[period_id]={id\|none}`, `/requests/preparing?filter[period_id]=` | as before; accredited queue only |
| POST | `/requests/{assessment}/remind` | `can:remind` (`requests.assign`, followed path), `throttle:sensitive` |

New notification codes: ACC-13, PER-01 … PER-05 (links `/paths/accredited`, `/paths/accredited/{id}?tab=request`, staff
`/admin/requests?path=accredited&stage=preparing`). Demo: `moashir:demo:install` (and `--insights-only`) lowers the demo
rollout to 2025-10-01, generates the periods and attaches them to the demo results.

## Evidence files
- `POST /assessments/{id}/evidence` (multipart `file`, `sub_criterion_id`, `attachment_key?`, `reason?`; `throttle:uploads`
  30/min): owner, `evidence.manage`, state `draft` (or `needs_completion` for the requested items). The limits are those of the
  request's PATH (`/settings/assessment/paths/{path}`): the extension, the server-detected MIME type and the magic bytes must
  match one of the path's allowed types (PDF, DOCX, XLSX, PPTX, JPG, PNG by default; 422 `file.content_mismatch`); size ≤ the
  path's `upload_max_file_mb`; at most the path's `evidence_max_files_per_sub` files (422 `attachment.sub_full`, `meta.max_files_per_sub`); the key must be a slot of the sub-criterion
  or of its guiding points (422 `attachment.key_invalid`). The file is stored `pending` on the private disk under a random
  name and `ScanStoredFileJob` is queued after commit (no in-request scan). Answer 201 `{ id (uuid), sub_criterion_id, code,
  attachment_key, file_name, mime_type, size_bytes, scan_status, uploaded_at }`.
- `GET /assessments/{id}/evidence?filter[sub_criterion_id]=` — the owner sees every present file with its scan status
  (`pending | clean | infected | failed`); every other reader (assessor, follower, System Administrator) sees clean files
  only.
- Infected after the background scan: when `ScanStoredFileJob` quarantines an evidence file it raises
  `Events\Files\StoredFileQuarantined`; `Listeners\Assessments\NotifyEntityOfRefusedEvidence` tells the owning entity
  IN-APP ONLY (new code EVD-02, link `/assessment/accredited`) that the file of sub-criterion `:code` in request `:number` was
  refused: variant `replace` ("remove it and upload a clean copy") while the request accepts entity content, variant
  `locked` ("the assessor does not see it and may ask for a clean copy") once it was submitted. Never the file name. Sent
  once per file (the quarantine is claimed once), not for removed evidence, closed or frozen requests or other file
  purposes. The Requests Follower is not notified; the System Administrators keep INT-05. `DELETE /assessments/{id}/evidence/{evidence}` (204) marks the row `removed` and deletes the binary after commit.
- `POST /assessments/{id}/evidence/{evidence}/download` → `{ url, expires_at, file_name }` (5 minutes, bound to the caller,
  Q-99); 409 `file.not_clean` until the scan is clean. `GET /files/{file}/download` re-checks the evidence policy and audits
  `file.downloaded` (Q-87).
- Bundles (Q-104): `POST /assessments/{id}/evidence/bundles` (staff with evidence access, not the entity; `throttle:exports`)
  zips the clean evidence (`<sub code>/<nnn>-<file name>`): up to `moashir.assessment.evidence_bundle_sync_max_bytes` (20 MB)
  it is built at once (201, `status: ready`), above that `BuildEvidenceBundleJob` prepares it (202, `status: preparing`) and
  EVD-01 tells the requester in-app when it is ready. `GET /assessments/{id}/evidence/bundles/{bundle}` and `POST
  …/{bundle}/download` (the requester only, 409 `evidence.bundle_not_ready`). Bundles expire after 24 hours and
  `moashir:assessments:purge-evidence-bundles` (hourly) deletes their files; 409 `evidence.bundle_empty` without clean evidence.

### Accredited endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/assessments/accredited/current` | `can:viewAccreditedCurrent` (entity + `assessment.accredited.manage`) |
| GET | `/assessments/{assessment}/form` | `can:view` (record scope) |
| PUT | `/assessments/{assessment}/cv` | `can:saveCv` (own, `assessment.accredited.manage`), `throttle:assessment-write` |
| POST | `/assessments/{assessment}/submit` | `can:submit` (own, path permission), `throttle:sensitive` |
| GET / POST | `/assessments/{assessment}/evidence` | `can:viewEvidence` / `can:manageEvidence`, `throttle:uploads` |
| DELETE | `/assessments/{assessment}/evidence/{evidence}` | `can:removeEvidence`, `throttle:assessment-write` |
| POST | `/assessments/{assessment}/evidence/{evidence}/download` | `can:downloadEvidence`, `throttle:downloads` |
| POST | `/assessments/{assessment}/evidence/bundles` | `can:requestEvidenceBundle`, `throttle:exports` |
| GET / POST | `/assessments/{assessment}/evidence/bundles/{bundle}`, `…/download` | `can:viewEvidenceBundle` (requester), `throttle:downloads` |
| GET | `/requests` | `can:viewRequestQueue` (`requests.queue.view`) |
| GET | `/requests/preparing` | `can:viewRequestQueue` (`requests.queue.view`) |
| GET | `/requests/assigned` | `can:viewAssignedRequests` (`requests.assigned.view`) |
| GET | `/requests/assessors` | `can:listAssessors` (`requests.assign`) |
| GET | `/requests/{assessment}` | `can:viewRequest` (queue view, or the assessor of the request) |
| POST | `/requests/{assessment}/assign`, `/reassign` | `can:assign` / `can:reassign` (`requests.assign`), `throttle:sensitive` |
| GET | `/assessments/{assessment}/review` | `can:review` (assignee with `assessment.score.manage`, or global reader with `requests.review.read`) |
| POST | `/assessments/{assessment}/start-review`, `/request-completion`, `/complete` | `can:score` (active assignee), `throttle:sensitive` |
| PUT | `/assessments/{assessment}/review/scores`, `/review/feedback` | `can:score`, `throttle:assessment-write` |
| POST | `/assessments/{assessment}/scores/override`, `/reopen`, `/transition/force` | `can:override` (Super Admin only), `throttle:sensitive` |
| GET / PUT | `/settings/assessment` | `can:settings.assessment-section.manage` (`settings.assessment.manage`), PUT `throttle:sensitive` |
| GET / PUT | `/settings/assessment/paths/{accredited\|advanced}` | same ability (+ `settings.finance.edit-field` for `price`), PUT `throttle:sensitive` |
| GET | `/content/assessment-paths/{accredited\|advanced}` | public, `throttle:public` |

Notification links (SPA routes): entity `/assessment/accredited`, `/assessment/accredited/result/{id}`,
`/assessment/accredited/result/{id}/feedback`; staff `/admin/requests/{id}`, assessor `/admin/requests/{id}/review`, bundle
`/admin/requests/{id}/evidence-bundles/{bundle}`. Audit events: `assessment.created`, `assessment.opened`,
`assessment.duplicate_payment`, `assessment.cv_saved`, `evidence.uploaded`, `evidence.removed`, `evidence.bundle_requested`,
`assessment.submitted`, `assessment.resubmitted`, `assessment.completion_expired`, `assessment.declined_incomplete`,
`assessment.assigned`, `assessment.reassigned`, `assessment.review_started`,
`assessment.scores_saved`, `assessment.feedback_saved`, `assessment.completion_requested`, `assessment.completed`,
`assessment.refunded`, `assessment.refund_after_completion`, `assessment.score_overridden`, `assessment.reopened`,
`assessment.transition_forced`, `assessment.due_dates_recomputed`, `settings.assessment_updated`,
`settings.periodic_updated`, `requests.reminder_sent`.

## Award cycles and the advanced path (build step 7, part 1)

The advanced path (Award) reuses the shared engine of the accredited path: the entity form, CV entries, evidence with the
background scan and signed links, the Requests Follower queue, the assignment, the assessor review with versioned scores and
feedback, the completion requests with their expiry outcome, the freeze guard and the status history. It adds the award cycles,
the entry conditions, the Follower's return for completion and withdrawal, and the filtering. The committee, the secretary
decisions, the regional and national results and the announcement are described in "Award committee, secretary, results and
announcement (build step 7, part 2)".

### Data
- `award_cycles`: bilingual `name`, `year`, `status`, the bound published `criteria_version_id`, `scale_kind` (copied from the
  criteria version unless given; every request of the cycle uses it, Q-81), `submission_start`, `submission_end`,
  `committee_start`, `committee_end`, `announcement_at` (planned, display only), `min_entity_age_years`,
  `disqualification_score` (`numeric(5,2)`), `initial_review_sla_days`, `participation_certificate_enabled` (Q-13),
  `created_by_user_id`, `opened_at`, `submissions_closed_at`, `closed_at`, `closed_from_status` (for the Super Admin reopen) and
  `announced_at` (set by the announcement of part 2). CHECKs order the dates and bound the values. The partial unique index
  `award_cycles_one_running` allows ONE cycle outside `draft` and `closed` (Q-37); further cycles can be prepared as drafts.
- `award_cycle_regions` (regions covered), `award_cycle_members` (cycle membership P-8: `filtering_officer | award_secretary`),
  `award_cycle_assessors` (initial assessors linked to the cycle, `is_active`), `award_cycle_reminders` (ADV-02 sent once per
  cycle and day threshold).
- `assessments` (path `advanced`, numbers `ADV-YYYY-######`): `award_cycle_id`, `region_id` (the entity region, refreshed at
  submission), `condition_failure` (the failed rules of a rejected application) and `withdrawn_at`. Partial unique indexes allow
  one request per entity and cycle and one recorded conditions rejection per entity and cycle. Snapshot kind
  `initial_assessment` holds the initial assessor's result.
- `filtering_decisions`: one append-only row per decision (`qualified | not_qualified`, initial percent, threshold, `is_override`,
  `reason`, `note`, decider). A CHECK makes the override flag follow the numbers and requires a reason for an override and for a
  disqualification.

### Cycle state machine (`AwardCycleStatus`)
`draft → submission_open → submission_closed → committee_assessment → awaiting_secretary → approved → announced`, and
`submission_closed | committee_assessment | awaiting_secretary | approved | announced → closed`. Part 1 drives `draft →
submission_open → submission_closed → closed`; part 2 drives `submission_closed → committee_assessment ⇄ awaiting_secretary → approved →
announced`.
- Open: manual (`POST …/open`) or by the scheduler at `submission_start`; a manual open before the start moves the start to now.
  Refused while another cycle runs (409 `cycle.another_open`, `meta.award_cycle_id`) or once the submission end passed (409
  `cycle.submission_window_passed`). ADV-01 (I+E) to every active entity of the cycle regions, the System Administrators and the
  Requests Followers.
- Close submissions: manual (early close, the end moves to now) or by the scheduler at `submission_end`. Unsubmitted drafts
  become `expired` (verb `expire_draft`, actor `system`, frozen drafts excepted). CYC-01 (I) to the System Administrators, the
  Requests Followers and the secretaries of the cycle.
- Reminders: `moashir:award:remind-submission-deadline` (hourly) sends ADV-02 (I+E) to the owners of the open drafts 7, 3 and 1
  days before the submission end (`moashir.award.submission_reminder_days`), once per threshold; thresholds passed together
  send one reminder.
- Close: from `submission_closed` onward, refused while any non-frozen request of the cycle is open (409
  `cycle.requests_pending`, `meta.open_requests[{ id, number, status }]`, Q-67 / Q-106). CYC-02 (I) to the System
  Administrators and the secretaries of the cycle.
- Reopen: Super Admin only (403 for everyone else), `reason` 20..1000, back to the state before the close, refused while another
  cycle runs; SEC-04 and audit `cycle.reopened`.
- Field locks (409 `cycle.field_locked`, `meta.locked_fields`): `submission_start`, `criteria_version_id`, `scale_kind` in
  `draft` only; `submission_end`, `region_ids`, `min_entity_age_years`, `disqualification_score` until submissions close;
  `committee_start`, `committee_end`, `initial_review_sla_days` until the committee assessment; the other fields until the
  cycle closes. Dates are re-checked as a whole (422 `cycle.dates_unordered`); a region with requests cannot be removed (422
  `cycle.region_has_requests`).

### Who sees and manages a cycle
- `award.cycles.view`: the System Administrator and the Requests Followers see every cycle while their request scope includes
  the `advanced` path (see "Path distinction"); filtering officers and secretaries
  see the cycles they are members of; assessors the cycles they are linked to. Other cycles answer 404.
- `award.cycles.manage`: the System Administrator for every cycle; an award secretary for the cycles they are a secretary member
  of. Creating a cycle makes a creating secretary a member (a System Administrator is global and is not added).
- Members and initial assessors: candidates are active staff accounts holding `award.filtering.decide` (filtering officer),
  `award.secretary.decide` (secretary) or `assessment.score.manage` plus the `advanced_initial` qualification (initial assessor;
  linking an unqualified assessor answers 422 `assessor.not_qualified` on `assessor_ids.<i>`), never a Super Admin. Segregation: a
  member is never an initial assessor of the same cycle and an initial assessor is never a member (422 `sod_conflict`, checked
  at linking, membership and assignment; SoD-2 / SoD-3 / P-06). The committee roster checks (SoD-1 / P-06) are described in "Award committee".
- The cycle detail adds `members[]` and `initial_assessors[]` only for its managers and the global readers.

### Advanced request (entity)
- `GET /assessments/advanced/current` → `{ cycle (public cycle) | null, eligibility { eligible, window_open, conditions[{ rule:
  min_entity_age | region, passed, required, actual }] }, request (the entity's request in the running cycle, with
  `condition_failure`) | null, previous (latest request of an earlier cycle) | null }`. The age is the Riyadh calendar year minus
  the founding year.
- `POST /assessments/advanced { award_cycle_id }`: 201 with the new draft, 200 with the existing draft, 409
  `cycle.submission_closed` outside the window, 409 `advanced.request_exists` (`meta.assessment_id`) after submission. Failed
  conditions record ONE `rejected_conditions` request (ADV-03, in-app) and answer 422 `cycle.conditions_failed` with
  `errors.conditions[]` and `meta.conditions[]`; a later application that meets the conditions opens a draft. The draft binds the
  criteria version and the scale of the cycle.
- Form, CV entries and evidence use the accredited endpoints (`/form`, `/cv`, `/evidence…`). Draft edits need the submission
  window open (409 `cycle.submission_closed`).
- `POST /assessments/{id}/submit`: same completeness check as the accredited path, window open, conditions checked again (422
  `cycle.conditions_failed`, the draft stays a draft) → `awaiting_review_assignment`, ADV-04 (I+E) to the entity and the
  Requests Followers. From `needs_completion` the resubmission goes back to `awaiting_review_assignment` and tells the Followers
  in-app (ADV-04 variant).
- `DELETE /assessments/{id}`: the entity deletes its OWN draft (Q-101): the row, its status history, CV entries, evidence rows and
  bundles are deleted and the files detached for the orphan purge; the audit `award.draft_deleted` stays. The status history
  trigger accepts this delete only for the one draft named in the transaction flag `moashir.draft_deletion`.
- Entity view before the announcement (Q-06, confidentiality matrix): `awaiting_filtering`, `qualified_for_committee` and
  `not_qualified` are shown as `under_review` with `is_open: true`, the matching history rows are hidden and a status filter on a
  hidden state finds nothing. An assessor who is not a results reader sees `awaiting_filtering` instead of the filtering outcome.

### Follower actions
- `POST /requests/{id}/return-for-completion { notes 10..2000, items[{ sub_criterion_id, kind: cv|evidence, note? }] 1..100,
  deadline_at (future, not after the cycle committee start — 422 completion.deadline_after_committee_start), expiry_outcome:
  continue_as_submitted|decline, reason? }` from `awaiting_review_assignment` only → `needs_completion`, ADV-25 (I+E) to the
  entity. One open return at a time, unlimited returns (each a completion request row). The entity edits only the listed items.
- Expiry (same command as the accredited path, `moashir:assessments:expire-completion-requests`): `continue_as_submitted` →
  `awaiting_review_assignment`, `decline` → `declined_incomplete`; ADV-27 (I+E) to the entity and the Requests Followers.
- `POST /requests/{id}/withdraw { reason 10..1000 }` from every submitted open state (Q-101; also for a frozen request, Q-106):
  ends the active assignment (`withdrawn`), cancels an open completion request, `withdrawn_at`, ADV-31 (I+E) to the entity, the
  Requests Followers and the assessor.

### Initial assessment
- The queue `GET /requests` serves one path at a time (`filter[path]=advanced`, or `filter[cycle_id]` which implies it; rows add
  `award_cycle` and `region`). `GET /requests/assessors?assessment_id=` lists only the active initial assessors of the cycle who
  hold the `advanced_initial` qualification and are not cycle members.
- `POST /requests/{id}/assign` (`awaiting_review_assignment → assigned`): 422 `assessor.not_in_cycle`, `sod_conflict`,
  `sod_self_assignment`; due date = assignment + the cycle SLA (`review_sla_days`); ADV-05 (I+E) to the assessor. Reassign keeps
  the status and the due date and tells the previous assessor in-app.
- `start-review` → `under_initial_assessment`; scores and feedback as for the accredited path; the assessor's
  `request-completion` is accredited only (409). `complete` → `awaiting_filtering` with the `initial_assessment` snapshot, the
  assignment completed and ADV-06 (I) to the filtering officers of the cycle and the Requests Followers. The overdue alert
  ACC-11 stays accredited only (no ADV code); `is_overdue` is shown for both paths.

### Filtering
- `GET /award/cycles/{cycle}/filtering` (`award.filtering.view`: the filtering officers and secretaries of the cycle, the System
  Administrator read only): requests in `awaiting_filtering | qualified_for_committee | not_qualified`, frozen ones excluded;
  `filter[status]`, `filter[region_id]`, `search`, `sort` ±`initial_percent` (default `-initial_percent`) | ±`submitted_at`;
  `meta { disqualification_score, counts{…}, below_threshold }`.
- `GET …/filtering/{assessment}`: `{ row, disqualification_score, assessor{ id, name }, review (read only), result (breakdown with
  feedback), decisions[] }`; evidence downloads follow the cycle membership.
- `POST …/qualify { note?, override_reason? (20..1000), reason? }` and `POST …/disqualify { reason 10..1000, note? }`
  (`award.filtering.decide`, filtering officers of the cycle only): allowed once submissions are closed (409
  `cycle.filtering_not_open`); a result below the disqualification score needs `override_reason` (422
  `filtering.override_reason_required`, `meta.score`, `meta.threshold`); the initial assessor of the request may not decide (403
  `sod_conflict`); frozen requests answer 409 `entity.deleted`. Qualify dispatches `Events\Award\EntityQualified` (the committee
  distribution of part 2 listens to it), sends ADV-07 (I+E) to the other filtering officers and, for an override, ADV-28 (I+E) to
  the secretaries of the cycle and the System Administrators. Disqualify closes the request and sends ADV-08 (I+E) to the other
  filtering officers. The entity-facing ADV-07 / ADV-08 are sent at the announcement. The filtering list and detail keep showing a qualified request
after it moved on (`filter[status]=qualified_for_committee` matches every state from `qualified_for_committee` onward).

### Lifecycle hooks
Open advanced requests are listed in the deletion impact (`cycle_id` set), frozen with the entity, their initial due date and
completion deadline are paused and resumed, and they are erased by `ReviewedRequestEraser` on permanent deletion (closed requests
stay on the tombstone, with their filtering decisions). Advanced requests follow the criteria version of their cycle: a criteria
publication (any scope) and the restore re-binding never move them, so the restore hold never applies to them.

### Award endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/award/cycles/current` | public, `throttle:public` |
| GET / POST | `/award/cycles` | `can:viewAny` (`award.cycles.view`) / `can:create` (`award.cycles.manage`), POST `throttle:sensitive` |
| GET | `/award/cycles/default-conditions` | `can:create` |
| GET | `/award/cycles/candidates?role=initial_assessor\|filtering_officer\|award_secretary&search=` | `can:listCandidates`, `throttle:search` |
| GET / PUT | `/award/cycles/{cycle}` | `can:view` / `can:manage`, PUT `throttle:sensitive` |
| PUT | `/award/cycles/{cycle}/members`, `/initial-assessors` | `can:manage`, `throttle:sensitive` |
| POST | `/award/cycles/{cycle}/open`, `/close-submissions`, `/close` | `can:manage`, `throttle:sensitive` |
| POST | `/award/cycles/{cycle}/reopen` | `can:reopen` (Super Admin only), `throttle:sensitive` |
| GET | `/award/cycles/{cycle}/filtering`, `/filtering/{assessment}` | `can:viewFiltering` / `can:viewFilteringRequest` |
| POST | `/award/cycles/{cycle}/filtering/{assessment}/qualify`, `/disqualify` | `can:decideFiltering`, `throttle:sensitive` |
| GET | `/assessments/advanced/current` | `can:viewAdvancedCurrent` (entity + `assessment.advanced.manage`) |
| POST | `/assessments/advanced` | `can:applyAdvanced`, `throttle:sensitive` |
| DELETE | `/assessments/{assessment}` | `can:deleteDraft` (own advanced draft), `throttle:sensitive` |
| POST | `/requests/{assessment}/return-for-completion`, `/withdraw` | `can:returnForCompletion` / `can:withdraw` (`requests.completion.return`), `throttle:sensitive` |

Notification links (SPA routes): entity `/assessment/advanced`; staff `/admin/requests/{id}`, assessor
`/admin/requests/{id}/review`, cycle `/admin/award/cycles/{id}`, filtering `/admin/award/cycles/{id}/filtering/{assessment}`.
Audit events: `cycle.created`, `cycle.updated`, `cycle.members_changed`, `cycle.initial_assessors_changed`, `cycle.opened`,
`cycle.submissions_closed`, `cycle.closed`, `cycle.reopened`, `cycle.reminders_sent`, `award.application_created`,
`award.application_rejected`, `award.submitted`, `award.resubmitted`, `award.draft_deleted`, `award.returned_for_completion`,
`award.withdrawn`, `award.initial_assessment_completed`, `award.qualified`, `award.qualified_override`, `award.disqualified`,
plus the shared `assessment.assigned`, `assessment.reassigned`, `assessment.review_started`, `assessment.scores_saved`,
`assessment.feedback_saved`, `assessment.completion_expired`, `assessment.declined_incomplete`.

## Award committee, secretary, results and announcement (build step 7, part 2)

Part 2 takes the qualified advanced requests through the assessors committee, the Award Secretary's decisions, the regional and
national results and the MANUAL announcement (Q-06). Certificates and the feedback-report PDF are a later step
(`Events\Award\NationalResultsApproved` and `Events\Award\CycleResultsAnnounced` are the hooks).

### Data
- `cycle_committee_assessors`: the committee seats of a cycle (`active | disabled | excluded | substituted | removed`), the seat a
  substitute replaces (`replaces_id`), the disabling reason and actor. One seat per user and cycle outside `removed`.
- `committee_assignments`: one row per seat and qualified request (`not_started | in_progress | completed | voided`), the replaced row
  of a substitute row and its approval, the submitted total (`percent`, `total_score`), the last secretary return and the void
  reason (`excluded | replaced | removed | withdrawn`).
- `committee_scores` and `committee_notes`: versioned like `assessment_scores` (only `superseded_at` may change; trigger + model
  guard). Notes: an optional note per sub-criterion and ONE general note per evaluation (`sub_criterion_id` null), Q-84.
- `committee_averages`: append-only averages (`version`, `percent`, `member_count`, `inputs[{ assignment_id, percent }]`,
  `inputs_digest`, `is_manual`); `assessments.committee_average_id` points at the current one.
- `secretary_decisions`: append-only ledger (`approve_average | return_to_committee | approve_substitute | approve_region |
  unapprove_region | approve_national | announce`, note mandatory for a return and an unapproval).
- `award_rankings` (one row per approved request: average, `regional_rank` 1..3 unique per cycle and region, `national_rank` 1..3
  unique per cycle) and `award_region_approvals` (one current approval per cycle and region, unapproval kept on the row).
- `committee_discussions` (`session | agreed_outcome`, `open | closed | cancelled`, one open per request) and
  `committee_discussion_entries` (`opinion | approve | request_edit`, versioned, one current entry per seat).
- `award_cycles` adds `committee_started_at`, `national_approved_at|by`, `announced_by_user_id`.

### Request states (part 2)
`qualified_for_committee → under_committee_assessment → awaiting_secretary_approval ⇄ committee_discussion`,
`awaiting_secretary_approval | committee_discussion → returned_to_committee → under_committee_assessment`,
`awaiting_secretary_approval → approved → regional_winner → qualified_for_national → national_winner | national_non_winner`,
`approved → non_winner`. A roster change that leaves a request with an unsubmitted counted evaluation moves
`awaiting_secretary_approval | committee_discussion` back to `under_committee_assessment` (open discussion cancelled, average pointer
cleared). Withdrawal (Follower, reason) is allowed up to `awaiting_secretary_approval` (and from the discussion and return states),
never from `approved` onward; it voids the committee rows and cancels the open discussion. `non_winner`, `national_winner` and
`national_non_winner` are terminal, so the cycle closes only after the national approval (Q-67).

Cycle: `submission_closed → committee_assessment` (manual `POST …/start-committee` or the scheduler at `committee_start`, at least one
active committee assessor, a manual start moves `committee_start` to now) `⇄ awaiting_secretary` (system: `awaiting_secretary` while
no non-frozen request of the cycle precedes the secretary review) `→ approved` (national approval) `→ announced` (manual).

### Committee roster (Q-08, Q-11, Q-38, Q-73)
- Managers: `award.committee.manage` held by the System Administrator or a Requests Follower (every cycle), or by a filtering
  officer / award secretary member of the cycle. Readers of the roster also include the secretary reviewers.
- Appoint `{ user_id }`: active staff holding `award.committee.score` (assessor role), never a Super Admin, one seat per cycle (422
  `committee.member_exists`, `committee.candidate_invalid`). SoD-1 / SoD-2 / SoD-3 / P-06: a cycle member (filtering officer,
  secretary) or an initial assessor of the cycle (linked or assigned) is never seated, and a seated assessor is never linked or
  assigned as initial assessor nor added as a member (422 `sod_conflict`); re-checked on every committee write (403).
- Distribution: when the committee starts, and after every qualification or appointment while it runs, every active seat gets one
  `not_started` row per qualified request (all assess all, ADV-09 with the number of new entities). No redistribution or
  reassignment exists (Q-73).
- Remove: only an active seat without started work or scores (422 `committee_assessor.has_scores`); its rows are voided.
- Disable `{ reason }` (ADV-14): the seat stops working (403 `committee.membership_inactive`); its rows still count until the manager
  chooses: exclude (rows of requests before approval voided, averages recomputed, 422 `committee.none_active` if no other active
  seat remains while requests are in committee) or substitute `{ substitute_user_id }` (ADV-15): a new active seat with a substitute
  row next to every replaced row of the requests in committee. The substitute reads the replaced scores and notes read only
  (`prior { scores, progress, percent, status, notes, view_only: true }`) and assesses on its own. The replaced row keeps counting
  until the secretary approves the completed substitute row (`…/substitutions/{assignment}/approve`, ADV-26); then the replaced row
  is voided (`replaced`), the average is recomputed and, once every substitute row of that seat is approved, the replaced seat
  becomes `excluded`.

### Committee assessment (blind, Q-57, Q-84, Q-91)
- `GET /committee/me/assignments` lists the caller's rows (voided rows and frozen requests excluded), with the entity per
  `committee_entity_data_visibility`: `name_only` `{ visibility, name, region }`, `code_only` `{ visibility, code, region }` (a stable
  pseudonymous `C-XXXXXXXX` code), `full` (summary + profile). Never the initial assessment, another assessor or the average.
- `GET /committee/me/assignments/{assignment}`: `{ assignment, mode: edit|read_only, assignment_id, status, scale, scores{ subs,
  points }, progress, percent, criteria (bound version), cv, evidence (clean), notes{ subs, general }, return_note | null, prior |
  null }`. The seated assessor may download the clean evidence (signed link) while the request is in committee.
- `PUT …/scores` (partial, on the cycle scale, versioned) and `PUT …/notes { notes{ "<sub id>": text|null }, general_note? }`: the
  first write starts the row (ADV-10) and a returned request resumes `under_committee_assessment`. `POST …/submit` needs every item
  scored (422 `scores.incomplete`), stores the own total, locks the row (409 `committee.evaluation_locked`) and sends ADV-11. When
  every counted row is submitted the average is computed automatically (mean of the counted totals in fixed point, half up) and the
  request moves to `awaiting_secretary_approval` (ADV-12, I+E). Writes need the committee stage (409 `cycle.committee_not_started`)
  and a committee state of the request; a frozen request answers 409 `entity.deleted`.
- Overdue: `moashir:award:flag-overdue-committee` (hourly) sends ADV-13 once per open row after `committee_end` (one notice per
  assessor with the count, plus the secretaries and System Administrators).

### Secretary (Q-12, Q-38, Q-65, Q-91)
- Review (`award.secretary.review`: cycle secretaries; System Administrator read only): `GET …/secretary/requests` (committee and
  result states, `filter[status|region_id]`, `search`, `sort` ±`submitted_at|average`, `meta.counts`; the awaiting list is
  `filter[status]=awaiting_secretary_approval`), the detail (initial result, every evaluation with the assessor, counted flag,
  substitute data, current average, decisions, discussions) and one evaluation (`scores`, `notes`, computed `result` breakdown).
- Decide (`award.secretary.decide`: cycle secretaries only): `…/average` idempotent recompute (same inputs → same version,
  `meta.recomputed`), `…/approve { note? }` (no pending substitute — 409 `committee.substitution_pending`, every counted row submitted —
  422 `committee.incomplete`; creates the ranking row; ADV-17 to the System Administrators and the committee; the entity copy waits
  for the announcement), `…/return { general_note 10..2000 }` (unlimited, every counted row reopens with its scores, average
  cleared, ADV-16 with the note), `…/substitutions/{assignment}/approve { note? }`.
- Discussion (optional): `…/discussions { mode: session, deadline_at }` or `{ mode: agreed_outcome, agreed_outcome }` from
  `awaiting_secretary_approval` (ADV-29 to the seated committee); members answer with `POST /committee/me/discussions/{id}/entries {
  kind: opinion (session) | approve | request_edit (agreed outcome), body (10..5000, not for approve) }` until the deadline (409
  `discussion.deadline_passed`, `discussion.closed`, 422 `discussion.entry_kind_invalid`; ADV-30 in-app to the secretaries); members
  see the entries without the other members' names; `…/discussions/{id}/close { summary? }` returns the request to
  `awaiting_secretary_approval`; a return during a discussion cancels it.

### Results and announcement
- `GET …/results` (regional board, `filter[region_id]`) and `GET …/national`: `[{ assessment_id, number, status, entity, region,
  average_percent, regional_rank, national_rank, approved_at }]` + `meta { cycle_status, regions[{ id, code, name, approved,
  approved_at, total, ranked, pending }], national{ approved, approved_at, announced_at } }`. Readers: cycle secretaries and the System
  Administrator (`award.results.view`).
- `PUT …/results/{assessment}/regional-rank { rank: 1|2|3|null }` (request `approved`, region not approved — 409 `award.region_locked`;
  422 `rank.taken` with `meta.assessment_id`, the holder is never swapped silently) and `PUT …/national/{assessment}/rank` (request
  `qualified_for_national`, national not approved — 409 `award.national_approved`). Secretaries only (`award.results.rank`).
- `POST …/regions/{region}/approve`: every non-frozen request of the region settled (409 `region.requests_pending`), at least one
  ranked (422 `winners.none`); ranked → `regional_winner → qualified_for_national`, others → `non_winner`; ADV-20 + ADV-21 in-app.
  `POST …/regions/{region}/unapprove { reason 20..1000 }`: Super Admin only (O-3, SEC-04), before the national approval; the region
  requests return to `approved` (national ranks cleared). `POST …/national/approve`: every other request settled (409
  `regions.pending`, `meta.regions_pending`), at least one national rank; ranked → `national_winner`, others → `national_non_winner`,
  cycle → `approved`, ADV-22, event `NationalResultsApproved`.
- `POST …/announce` (Q-06): the cycle secretaries holding `award.results.announce` only (the System Administrator holds the key but
  is refused; Super Admin by bypass with a reason): cycle `approved → announced`, `announced_at`. The status mask lifts at once, the
  entity result (`GET /assessments/{id}/result`) becomes available for `not_qualified`, `non_winner`, `national_winner` and
  `national_non_winner` (initial assessment result and feedback + `award { status, region, regional_rank, national_rank }`; never the
  committee average or names), and every participant receives the held ADV-07 / ADV-08 copy, ADV-18 and, when it reached the
  regional / national stage, ADV-23 / ADV-24 (winner with the rank or non-winner); ADV-18 also to the System Administrators.
  ADV-19 / ADV-32 belong to the certificates step.

### Session and visibility
`GET /auth/session` fills `committee_appointments[{ id, award_cycle_id, cycle_name, cycle_status, status, is_substitute,
committee_start, committee_end }]` (active seats of cycles that are not closed) through `App\Contracts\Identity\
CommitteeAppointmentsReader`. A committee assessor never reads the request record (`/assessments/{id}`) or its result; the cycle
list includes the cycles where the user holds a seat. Before the announcement the entity reads every post-filtering state as
`under_review`, and an assessor who is not a results reader reads `awaiting_filtering`.

### Committee endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| POST | `/award/cycles/{cycle}/start-committee`, `/announce` | `can:manage` / `award.results-board.announce`, `throttle:sensitive` |
| GET | `/award/cycles/{cycle}/committee` | `award.committee-roster.view` |
| GET | `/award/cycles/{cycle}/committee/candidates?search=` | `award.committee-roster.manage`, `throttle:search` |
| POST | `/award/cycles/{cycle}/committee/members` | `award.committee-roster.manage`, `throttle:sensitive` |
| DELETE / POST | `/award/cycles/{cycle}/committee/members/{member}`, `/disable`, `/exclude`, `/substitute` | `award.committee-roster.member`, `throttle:sensitive` |
| GET | `/committee/me/assignments`, `/committee/me/discussions` | `award.committee-work.view` (`award.committee.assigned.view`) |
| GET | `/committee/me/assignments/{assignment}` | `can:view` (own seat) |
| PUT / POST | `/committee/me/assignments/{assignment}/scores`, `/notes`, `/submit` | `can:work` (own seat, `award.committee.score`), `throttle:assessment-write` / `throttle:sensitive` |
| POST | `/committee/me/discussions/{discussion}/entries` | `can:respond` (seated, active), `throttle:sensitive` |
| GET | `/award/cycles/{cycle}/secretary/requests`, `/{assessment}`, `/{assessment}/evaluations/{assignment}` | `award.secretary-desk.review` / `.evaluation` |
| POST | `/award/cycles/{cycle}/secretary/requests/{assessment}/average`, `/approve`, `/return`, `/discussions` | `award.secretary-desk.decide`, `throttle:sensitive` |
| POST | `/award/cycles/{cycle}/secretary/requests/{assessment}/substitutions/{substitution}/approve` | `award.secretary-desk.substitution`, `throttle:sensitive` |
| POST | `/award/cycles/{cycle}/secretary/requests/{assessment}/discussions/{discussion}/close` | `award.secretary-desk.discussion`, `throttle:sensitive` |
| GET | `/award/cycles/{cycle}/results`, `/national` | `award.results-board.view` |
| PUT | `/award/cycles/{cycle}/results/{assessment}/regional-rank`, `/national/{assessment}/rank` | `award.results-board.rank`, `throttle:sensitive` |
| POST | `/award/cycles/{cycle}/regions/{region}/approve`, `/national/approve` | `award.results-board.approve-region` / `.approve-national`, `throttle:sensitive` |
| POST | `/award/cycles/{cycle}/regions/{region}/unapprove` | `award.results-board.unapprove-region` (Super Admin only), `throttle:sensitive` |

Notification links (SPA routes, assumptions): committee `/admin/committee/assignments`, `/admin/committee/discussions`, roster
`/admin/award/cycles/{id}/committee`, secretary `/admin/award/cycles/{id}/secretary/{assessment}`, results
`/admin/award/cycles/{id}/results`, entity `/assessment/advanced/result/{id}`. Audit events: `cycle.committee_assessor_appointed`,
`cycle.committee_assessor_removed`, `cycle.committee_assessor_disabled`, `cycle.committee_assessor_excluded`,
`cycle.committee_assessor_substituted`, `cycle.substitute_approved`, `cycle.committee_started`, `cycle.stage_changed`,
`cycle.criteria_version_moved`, `cycle.announced`, `award.distributed`, `committee.started`, `committee.scores_saved`,
`committee.notes_saved`, `committee.submitted`, `award.average_computed`, `award.average_approved`, `award.returned_to_committee`,
`committee.discussion_opened`, `committee.discussion_entry`, `committee.discussion_closed`, `award.regional_rank_set`,
`award.region_approved`, `award.region_unapproved`, `award.national_rank_set`, `award.national_approved`.

## Integrations (client-configured gateways)

The client enters every key and option of the external gateways in the System Administrator settings screens; going
live needs no code change and no deployment. Providers: Moyasar (payments), Taqnyat and Msegat (SMS, one active at a
time), SMTP (e-mail) and OpenAI or Anthropic (AI provider of the public assistant). Google Gemini and embeddings are not
part of this step (operator, session 11).

### Storage
- `integration_credentials` (model `Models\Integrations\IntegrationCredential`): one row per `(kind, provider, mode)`;
  Moyasar has one row per key-pair mode (`test`, `live`), the other providers one row each whose `mode` follows the kind
  mode. `public_config` holds the non-secret fields, `secrets` the secrets (`encrypted:array` cast, AES-256-CBC + MAC with
  `APP_KEY`, never serialized: the model hides it and the API Resources never read it), `secret_meta` the write-only state
  of each secret (`set`, `last4` for API keys of 20 characters or more, `updated_at`, `updated_by_user_id`), `provider_refs`
  the non-secret provider references (Moyasar `webhook_id`, approved Msegat templates, the AI cross-border
  acknowledgement), `config_version` (+1 on every change) and the last connection test (`last_test_*`). A partial unique
  index allows one active row per kind; CHECKs pin the provider to its kind.
- `settings` (model `Models\Settings\Setting`, code-defined keys in `Enums\Settings\SettingKey`, defaults in code): the
  non-secret behaviour of each kind, `payment.mode | methods | supported_networks | apple_pay_label | linked_paths |
  checkout_pattern | apple_pay_domain_confirmation | stcpay_activation_confirmation | order_validity_minutes`,
  `sms.mode | test_recipients | templates`, `email.mode | test_address`, `ai.mode | placement | notice`. Every change is
  audited with its before and after values. The integration keys are never stored here.

### Resolution rule
`Services\Integrations\IntegrationCredentialsResolver`:
1. the active row of the kind, when every required field of its provider is present (`source = settings`);
2. only when NO row exists for the kind and `INTEGRATIONS_ENV_FALLBACK=true`: the bootstrap values read through
   `config('moashir.integrations.environment')` and `config('mail.mailers.smtp')` (`source = environment`); fields are never
   mixed between a row and the environment, and the first save in the UI takes over;
3. otherwise not configured: SMS is skipped and sign-in codes go by e-mail (INT-01 on a failed send), e-mail answers with a
   failure and INT-04 outside `local` (the `log` transport in `local`), payment and AI calls refuse to start.

`php artisan moashir:integrations:import-env {payment|sms|email|ai}` copies a complete bootstrap into an encrypted, active
row (audited `integration.env_imported`, field names only) and refuses when the kind already has rows; remove the values
from `.env` afterwards. After an `APP_KEY` rotation (old key in `APP_PREVIOUS_KEYS`) run
`php artisan moashir:integrations:reencrypt` (audited `integration.secrets_reencrypted`).

Only the `config_version` sum of a kind is cached (`integrations:config_version:<kind>`, forgotten after commit); decrypted
secrets never reach the cache, the session, a queue payload or a log. Each SMS, payment and AI call reads and decrypts the
row in-process; the `settings` mail transport keeps its SMTP connection while the version is unchanged and rebuilds it
when the version changes, so long-lived Horizon workers pick up new credentials. The OTP notification is queued with an
encrypted payload (`ShouldBeEncrypted`) and stops retrying once its code expires.

### Secrets
- Write-only: every GET returns `{ set, last4, updated_at, updated_by{ id, name } }`; a PUT accepts the raw value; an
  omitted, empty or `null` secret means "keep"; `{"clear": true}` deletes it. Secrets never appear in responses, validation
  messages, test results, audit diffs, notifications, GlitchTip events or logs.
- Replacing or clearing a secret writes `security.secret_replaced` (`kind`, `provider`, `mode`, `field`, `action` only) and
  raises SEC-06 to every Super Admin and the security mailbox. A Super Admin must give a `reason` (O-14, 422
  `reason_required`). An AI key or provider change also sends AI-04 to the other System Administrators.
- GlitchTip scrubbing (`Support\Monitoring\SentryEventScrubber`) removes bearer tokens, Moyasar and AI keys and any
  `apiKey`, `userName`, `secret_token`, `shared_secret`, `x-api-key`, `Authorization` or `password` value from messages and
  breadcrumbs, and drops breadcrumb query strings and bodies. Outbound calls are logged as
  `integration.outbound_call {provider, operation, status, latency_ms, request_id, provider_request_id}` only.
- The four permissions `settings.payment.manage`, `settings.sms.manage`, `settings.email.manage` and `ai.provider.manage`
  are hard-locked to `system_admin` (never grantable, `moashir:permissions:sync` applies the lock); the Super Admin passes
  through `Gate::before`.

### Test and live mode
| Kind | test | live |
|---|---|---|
| Payment | the TEST key pair (`pk_test_` / `sk_test_`); switching to test in production needs a `reason` (audited `integration.mode_changed`) and sets `is_test_mode_in_production` (payment GET) and `meta.payment_test_mode_in_production` (overview) for the SPA banner | the LIVE key pair; selecting live needs a complete live pair that passed its connection test |
| SMS | real sends only to `sms.test_recipients` (at most 5 Saudi mobile numbers in E.164); every other recipient is suppressed (`suppressed_test_mode`, logged without the number) | all recipients |
| E-mail | every message goes to `email.test_address` (To replaced, Cc and Bcc removed); switching needs the test mailbox | normal delivery |
| AI | stored for the assistant (admin preview only); the public assistant itself is not part of this step | public per `ai.placement` |

Activation: payment needs a passing test of the selected mode; SMS, e-mail and AI need every required field; AI also
needs the cross-border acknowledgement of that provider (`data_transfer_acknowledged`, recorded with user and time). A
change of a tested field resets the last test of that row (a webhook secret does not).

### Connection tests
`POST …/test-connection` (3 per minute per user and endpoint, like every load and test-send endpoint) may target a
non-active provider or mode. Result: `{ ok, provider, mode, latency_ms, checks[{ key, ok, detail_code }], info{}, error_kind }`.
A failure answers 502 `provider_error` with `errors.provider_error_kind` and the full result in `meta.result`. Each test
updates `last_test_*`, writes `settings.<kind>_tested` and notifies the other System Administrators: INT-02 (SMS passed),
INT-01 (SMS failed), INT-03 (payment failed), AI-05 (AI failed). No test charges money or sends a message.

| Provider | Checks |
|---|---|
| Moyasar | `key_prefixes`, `secret_key_valid` (`GET /v1/webhooks`; `info.webhook_registered`), `intents_available` (a 1.00 SAR intent created and cancelled, checkout pattern `intent`) |
| Taqnyat | `service_status` (`GET /system/status`), `credentials_valid` and `account_usable` (`GET /account/balance`; balance, currency, account status and expiry in `info`), `sender_active` (`GET /v1/messages/senders`) |
| Msegat | `credentials_valid` and `account_usable` (`Credits.php`); the sender is proven by the test SMS |
| SMTP | `host_allowed`, `handshake` (`start()` then `stop()`, no message); `info.spf_record_found`, `info.dmarc_policy` (advisory) |
| OpenAI, Anthropic | `api_key_valid` and `model_available` (model list); "Test answer" sends one fixed prompt limited to 16 output tokens |

### Outbound baseline
- Laravel HTTP client only (no provider SDK), through `Services\Integrations\ProviderHttp`: HTTPS to a code-defined host,
  TLS verification on, no redirects, connect timeout 5 s, timeout 15 s (payment, SMS, model lists) and 30 s or the provider
  setting (AI answer). Retries only for transient errors (`unavailable`, `timeout`, `rate_limited`): Moyasar payment fetch 3
  retries (1, 2, 4 s + jitter), intent creation 2, AI answers 1 (5xx, 529, or 429 with `Retry-After` of at most 2 s); webhook
  registration and quota or authentication errors are never retried. Queued SMS retry on transient failures only; a
  permanent SMS failure raises INT-01 (at most once per 10 minutes per provider and kind) and sends the sign-in code by
  e-mail instead.
- Egress allow-list (`Support\Integrations\EgressAllowList`): `api.moyasar.com`, `api.taqnyat.sa`, `www.msegat.com`,
  `api.openai.com` and the regional `us. eu. ae. au. ca. jp. in. sg. kr. gb.api.openai.com`, `api.anthropic.com`. There is no
  free-text base URL anywhere. The firewall of the server should allow the same hosts.
- SMTP is the only free-text host: it must be a host name (no IP literal), every resolved address must be public
  (loopback, RFC 1918, link-local, CGNAT `100.64.0.0/10`, IPv6 ULA and link-local, documentation, multicast and reserved
  ranges are refused, IPv4-mapped IPv6 included), and the port one of 25, 465, 587, 2525. The check runs on save and again
  at connection time; the socket connects to the vetted address while TLS verifies the certificate of the host name.
- Error kinds (`Enums\Integrations\ProviderErrorKind`): `auth_failed, account_inactive, misconfigured, recipient_invalid,
  rejected, quota_exhausted, rate_limited, unavailable, timeout, request_too_large, unknown`. Settings endpoints answer 502
  `provider_error`; a provider failure that reaches a user flow answers 503 `provider_unavailable`; provider names and
  messages are never shown to the public.

### Integration endpoints (`/api/v1/settings/...`)
| Method | Path | Ability / limiter |
|---|---|---|
| GET | `integrations` (overview `{ kind, provider, active, mode, source, configured, last_test }[]`, `meta.payment_test_mode_in_production`) | holder of any of the four permissions |
| GET / PUT | `payment` | `settings.payment.manage`; PUT `throttle:sensitive` |
| POST | `payment/test-connection` `{ mode? }`, `payment/webhook/register` `{ mode, reason? }` | `settings.payment.manage`, `throttle:integration-probe` (3/min) |
| POST / DELETE | `payment/apple-pay/association-file` (multipart `file`, plain text, at most 64 KB) | `settings.payment.manage`, `throttle:sensitive` |
| POST | `payment/apple-pay/association-file/check` | `settings.payment.manage`, `throttle:integration-probe` |
| GET / PUT | `sms` | `settings.sms.manage`; PUT `throttle:sensitive` |
| POST | `sms/{taqnyat|msegat}/test-connection`, `sms/test` `{ recipient, provider? }` | `settings.sms.manage`, `throttle:integration-probe` |
| GET | `sms/taqnyat/senders`, `sms/msegat/templates` | `settings.sms.manage`, `throttle:integration-probe` |
| GET / PUT | `email` | `settings.email.manage`; PUT `throttle:sensitive` |
| POST | `email/test-connection`, `email/test` `{ recipient }` | `settings.email.manage`, `throttle:integration-probe` |
| GET / PUT | `ai` | `ai.provider.manage`; PUT `throttle:sensitive` |
| GET | `ai/models?provider=openai|anthropic` | `ai.provider.manage`, `throttle:integration-probe` |
| POST | `ai/test-connection` `{ provider? }`, `ai/test-answer` `{ provider? }` | `ai.provider.manage`, `throttle:integration-probe` |
| GET | `/.well-known/apple-developer-merchantid-domain-association` (outside `/api`) | public, `throttle:public`; the uploaded file byte for byte as `text/plain`, 404 when absent |

Every PUT is partial (omitted fields keep their value) and answers with the full GET shape. The request and response
shapes are listed in `agents/tasks/reports/Task-16-report.md`.

### Contracts and adapters
`Contracts\Integrations\PaymentGateway` (create intent, fetch payment, cancel intent; amounts cross the contract as
`Money` in decimal riyals; `Money::inMinorUnits()` gives the halalas that `Integrations\Moyasar\MoyasarAmount` sends and that the
checkout object shows),
`PaymentWebhookVerifier` (`MoyasarWebhookVerifier`: the payload `secret_token` compared in constant time with the secret
of the mode named by `live`, payloads above 256 KB refused, `secret_token`, `data.ip`, `data.source.name`,
`data.source.mobile` removed and `data.source.number` cut to its last 4 digits before anything is stored),
`PaymentWebhookRegistrar`, `SmsGateway` (now `send(SmsMessage): SmsSendResult`, throwing `SmsPermanentFailure` or
`SmsTransientFailure`), `SmsProviderDriver`, `SmsAccountInspector`, `SmsSenderDirectory`, `SmsTemplateDirectory`,
`IntegrationConnectionTester`, `AiProvider` (OpenAI Responses with `store: false`; Anthropic Messages with the pinned
`anthropic-version: 2023-06-01` and `inference_geo` only when `us`; no user identifier, no metadata, no tools),
`AiModelCatalog`, `SmtpTransportFactory`, `HostResolver`, `SmsAvailability`. Fakes for tests and later domains:
`Integrations\Fakes\FakePaymentGateway`, `FakeSmsGateway`, `FakeAiProvider`. The Moyasar webhook route and the payments
domain (orders, checkout, verify, webhook, receipts) is described in "Payments and finance".

### Steps the client must do at the provider (keys cannot replace them)
| Provider | Steps outside the platform |
|---|---|
| Moyasar | Activate the account for live (live keys appear only after activation). Apple Pay: in the Moyasar dashboard, Settings, Apple Pay Domains, add the host shown on the screen (`moashir.ttshub.live` in production, `staging.moashir.ttshub.live` on staging with the TEST account), download the association file, upload it on the payment screen, use "Check file is reachable", then Validate and Register in Moyasar and tick the confirmation. STC Pay: request it from the Moyasar account manager, then tick the confirmation. Recommended: add `SERVER_EGRESS_IPS` to the Moyasar IP whitelist of each mode. Only for a manual webhook: create it in the dashboard with the URL, method POST and all events shown on the screen and type the same secret token into the write-only field. |
| Taqnyat | Register the sender name (sender form from the account manager, company letterhead, valid company certificate, operator approval); enable sending by API and authorize the server IP (Developers, Security Settings); keep the balance topped up. |
| Msegat | Activate the sender name (at most 11 English letters or digits); register the static server IP if Msegat enforces it (M0037); if the sender is restricted to templates, have them approved, then "Load Msegat templates" and tick "template restricted"; keep the balance. |
| SMTP | A mailbox or relay account at the client's mail provider; SPF, DKIM and DMARC records on the sending domain (client DNS). |
| OpenAI, Anthropic | Provider account, billing and spend limits in the provider console; optional data-retention or regional contracts with the provider's sales team. |

### Deployment (one-time, our team)
- Nginx: route `location = /.well-known/apple-developer-merchantid-domain-association` to Laravel (PHP-FPM) on every host
  that offers Apple Pay; today only `/api` and `/sanctum` are proxied.
- Set `MAIL_MAILER=settings` in staging and production so platform mail uses the e-mail settings screen; `log` stays the
  local default.
- Set `SERVER_EGRESS_IPS`; keep `INTEGRATIONS_ENV_FALLBACK` off unless a bootstrap is wanted.
- The SPA CSP for the Moyasar form: see the deployment notes of "Payments and finance".
- To verify in the sandbox before go-live (Q-110, Q-115): webhooks per mode and for test payments, Msegat request encoding
  and response bodies of `sendsms.php` and `Credits.php`, the Msegat template placeholder syntax, Taqnyat success codes.

## Payments and finance (build step 5)

Payments use the Moyasar Form with a server-side payment intent (pattern A1, operator Q-109): the amount is locked on
the server, the browser never decides a status, and the webhook stays the source of truth (Q-100). Everything goes
through the `PaymentGateway` contract; tests use `Integrations\Fakes\FakePaymentGateway` and `Http::fake()` only.

### Data
- `payments`: `public_id` (uuid, used in every URL), `reference` `PAY-YYYY-######`, `entity_id`, `entity_name` (legal
  name at payment time), `initiated_by_user_id`, `purpose` (`accredited | bag`; leadership is refused, Q-25), `mode`
  (`test | live`, copied from the active gateway), `idempotency_key` (unique per user), `provider_intent_id`,
  `checkout_client_secret` (encrypted, hidden, cleared when the order closes), `provider_payment_id` (unique, bound once),
  `method` (`creditcard | mada | applepay | stcpay`), `card_last4`, the price snapshot `price_before_vat`, `vat_rate`,
  `vat_amount`, `total` (`numeric(12,2)`, decimal strings, never floats), `currency`, `status`, `expires_at` and the
  state timestamps (`bound_at`, `verified_at`, `paid_at`, `failed_at`, `cancelled_at`, `refunded_at`, `late_paid_at`,
  `manually_settled_at`), `failure_reason` / `failure_code` / `failure_message`. CHECKs: `total = price_before_vat +
  vat_amount`, `pending` needs a bound provider payment, `paid` and `refunded` need `paid_at`, a late payment only on a
  cancelled order; a partial unique index allows one open order (`created | pending`) per entity and service. Payments
  are never deleted (10-year retention, `production-roles.sql` revokes DELETE).
- `payment_events`: every checkout, verification, webhook, expiry, reconciliation and admin event; unique `(provider,
  provider_event_id)`; redacted payload (no secret token, card holder name, mobile or IP; card number cut to the last 4).
  A trigger allows only the processing columns to be set once; nothing is deleted.
- `payment_receipts`: one per paid payment, `number` `RCP-YYYY-######` (gap-free, see below) or `null` with `is_test`
  for test payments (Q-111), frozen `snapshot`, `pdf_file_id`, `reissue_count`, `voided_at` / `void_reason` /
  `voided_by_refund_id`. A trigger keeps the number, snapshot and issue date immutable and a void final.
- `refunds`: recorded refunds only (Q-50/Q-60): `amount` = payment total, `refunded_on`, `reason`, `transfer_reference`,
  `evidence_file_id` (mandatory, scanned), `recorded_by_user_id`. Append-only (model, trigger, grants).
- `checkout_intents`: one open row per entity and service, touched when an order is opened and converted when it is
  paid; feeds the abandoned-cart list; removed by the permanent entity deletion (`CheckoutIntentEraser`).
- `finance_exports`: every transactions export (filters, row count, status `streamed | queued | ready | failed`, file).
- `stored_files` (Files foundation, see below).

### Pricing
`Services\Finance\PriceCalculator`: price before VAT from `Contracts\Finance\ServicePriceSource`
(`SettingsServicePriceSource`: settings `assessment.accredited_price` and `bag.price`, a seam until the Kit product
exists), one VAT rate (`assessment.vat_rate`, default `15.00`), VAT = price × rate rounded half up to the halala
(`VatRate`, integer arithmetic), total = price + VAT. The quote is copied onto the payment. No gateway fee anywhere (Q-22).
A service without a positive price answers 409 `payment.price_not_configured`.
`GET /api/v1/content/prices` (public, guests included — 03 §7.1 and §10.4 show the live fee, VAT and total before sign-in;
`throttle:public`, 120/min per IP) answers `{ accredited: Pricing | null, bag: Pricing | null }` with `Pricing = {
price_before_vat, vat_rate, vat_amount, total, currency }` computed by the same quote rules (`ServicePriceQuotes`,
`PriceQuote`; decimal strings, `null` while a price is unset). Nothing else from the settings is exposed. The services paid through the gateway
follow the setting `payment.linked_paths`. The prices, the VAT rate and the order validity are edited through the
finance settings below (no database edit is needed in staging).

### Finance settings (`/api/v1/settings/finance`)
- `GET` answers `{ currency: "SAR", accredited_price, bag_price, vat_rate, order_validity_minutes, order_validity_limits
  { min: 5, max: 60 }, pricing { accredited, bag }, editable_fields[] }`. Prices are before VAT, decimal strings with two
  places or `null` while unset; `vat_rate` is a percentage string (`"15.00"` by default); `pricing.*` is
  `{ price_before_vat, vat_rate, vat_amount, total, currency }` computed with the payment quote rules (`PriceQuote`, or
  `null`), so the SPA never computes VAT; `editable_fields` lists the fields the signed-in user may change (hide-don't-forbid).
- `PUT` is partial (omitted fields keep their value) and answers with the `GET` shape: `accredited_price` and
  `bag_price` (`"1500.00"`: two decimals, at most 7 integer digits, greater than zero), `vat_rate` (string, 0 to 100,
  at most two decimals, normalized to `"15.00"`), `order_validity_minutes` (integer 5..60), `reason` (10..1000,
  optional for the System Administrator, REQUIRED for the Super Admin: 422 `reason_required`, and SEC-04 is raised
  after a reasoned change). Invalid input answers 422 `validation_failed` with translated field messages.
- Permissions (analysis-permissions): the route needs any of the field permissions; `accredited_price`, `vat_rate` and
  `order_validity_minutes` need `settings.assessment.manage`, `bag_price` needs `bag.admin.manage` (Kit product
  section). Both are seeded to the System Administrator only; the Super Admin passes through `Gate::before`. A body
  carrying a field the user may not change answers 403 `forbidden` and changes nothing.
- Every changed key writes one `settings.finance_updated` audit with its old and new value (and the reason); an unchanged
  value writes nothing. Concurrent saves are serialized by a transaction-scoped advisory lock (`settings:finance`).
- Snapshot rule: a payment copies its price, VAT rate, VAT, total and `expires_at` when the order is opened, so a change
  never alters an existing payment or open order; only orders opened afterwards use the new values.
- Platform VAT registration number (Task-64, Q-155 / Q-156): field `vat_registration_number` (setting
  `finance.vat_registration_number`, string or `null`) guarded by the locked `settings.payment.manage` (System
  Administrator; Super Admin with `reason`). Format `^3[0-9]{13}3$` (15 Western digits, first and last `3`, no spaces;
  Arabic-Indic digits are refused) else 422 on `vat_registration_number`; sending `null` or `""` clears it at any time.
  Audited as `settings.finance_updated` (before / after; not a secret). The `GET` shape adds `vat_registration_number`
  and `vat_registration_reminder` (`true` while the VAT rate is above zero and the number is empty — a non-blocking hint
  for the hub). An empty number never blocks an order, a payment or a receipt.

### State machine
`created → pending` (provider payment bound) `→ paid | failed | cancelled`; `created → cancelled`; `paid → refunded`
(recorded refund only). Illegal moves answer 409 `state.transition_illegal`. `PaymentStateMachine` stamps the state time
and clears the checkout secret when the order closes. A verified `paid` for an order already `cancelled` keeps it
cancelled, sets `late_paid_at`, audits `payment.late_paid` and sends PAY-08 to the Finance Officers and System
Administrators, who return the money outside the platform and record the refund (Q-112).

### Checkout, verification, webhook
1. `POST /payments { purpose }` with header `Idempotency-Key: <uuid>` (422 `idempotency_key.missing`). The entity must be
   active (409 `payment.entity_not_active`, frozen 409 `entity.deleted`). The order is created `created` with
   `expires_at = now + payment.order_validity_minutes` (default 30, clamped to 5..60 because Moyasar intents live 1 h),
   then the intent is created outside the transaction. A repeated key returns the same order (`meta.idempotent_replay`;
   another purpose → 422 `idempotency_key.reused`); an open order of the same service is returned with 200
   (`meta.existing_order`). No active gateway → 503 `integration.not_configured`; a gateway failure cancels the new order
   (`failure_reason = checkout_unavailable`) and answers 503 `provider_unavailable`. The client secret is stored encrypted
   for replays only and never logged, audited or stored in an event.
2. `POST /payments/{payment}/verify { provider_payment_id }`: fetches the payment with the secret key of the payment's
   mode, requires `metadata.moashir_payment_id` = our id (else 409 `payment.verification_rejected`, INT-04), binds the
   provider id once (another id → 409 `payment.binding_mismatch`), then maps the provider status: `initiated` → `pending`
   (202), `paid` → `paid` when amount and currency match (else `failed` with `amount_mismatch` + PAY-08), `failed` →
   `failed` with a reason group from the response code, `refunded | voided` → ignored + PAY-09, other statuses → ignored
   + INT-04. A concluded payment answers with its state without calling the gateway.
3. `POST /webhooks/moyasar` (public, no session, no CSRF, any Accept header, ≤ 256 KB else 413, `throttle:webhooks`
   120/min per IP): `MoyasarWebhookVerifier` (constant-time `secret_token` of the mode named by `live`; 401 / 400), the
   redacted event is stored once (a duplicate answers 200 `{ received: true, duplicate: true }`), matched by our id then
   by the provider id, and `ProcessGatewayWebhookJob` (unique per payment until processing, no overlap per payment, 5
   tries) re-fetches the payment and applies the same rules. Unmatched events are stored as `unmatched`; only unmatched
   LIVE events raise INT-04. Rejected deliveries are logged (`payment.webhook_rejected`, code and IP) and not stored.
4. `ExpirePaymentsJob` (every minute): a `created` order is cancelled at `expires_at` after its intent is cancelled (a
   transient gateway error retries on the next run; an intent that can no longer be cancelled still closes the order); a
   `pending` order is re-fetched and settled, failed, or kept for a 15-minute grace window, then cancelled. PAY-07 goes
   to the entity. `ReconcilePendingPaymentsJob` (every 5 minutes) re-fetches pending payments untouched for 5 minutes
   and re-dispatches webhook events still unprocessed after 10 minutes.

### Receipts
`ReceiptIssuer` runs in the same transaction as the `paid` transition. `ReceiptNumberGenerator` takes the next value of
the per-year counter `finance.receipt` in `yearly_number_sequences` (Asia/Riyadh year); the upsert locks the counter row
until the transaction commits, so concurrent issuers wait and a rolled-back issue leaves no gap (it refuses to run
outside a transaction). Test-mode payments get a receipt without a number (`is_test`, watermark `TEST`), excluded from
every statistic, summary and export. PDFs go through `Contracts\Documents\PdfRenderer` (`RenderReceiptPdfJob` after
issue, or on the first download): bilingual labels, Gregorian and Umm al-Qura dates with Western digits, watermark
`TEST` or `VOID`, printed by the Chromium renderer (template `documents.payment_receipt`, see "PDF documents"). Without a
renderer (`UnavailablePdfRenderer`) the download answers 503 `document.renderer_unavailable` and the job defers. Reissue
(`finance.receipts.manage`, reason) renders a new file and keeps the previous one; a voided receipt cannot be reissued
(409 `receipt.voided`).

VAT registration number on receipts (Task-64): `ReceiptSnapshotBuilder` copies the platform number into
`snapshot.vat_registration_number` at issue time ONLY when it is set (the key is absent otherwise); a reissue renders
the stored snapshot, so the issue-time value never changes. The receipt resource adds `vat_registration_number`
(`null` when absent) and the PDF prints the row in the header block only when it is present. No "not a tax invoice"
line. `receipt.issued` records `vat_registration_number_present`.

### Refunds (recorded only)
`POST /finance/payments/{payment}/refunds` (multipart: `amount` = total, else 422 `refund.amount_must_equal_total`;
`refunded_on` between the payment date and today; `reason` 20..1000; `transfer_reference` 3..80; `evidence` PDF, JPG or
PNG within `files.max_size_mb`): the file is checked, stored privately and scanned BEFORE the transaction (422
`file.content_mismatch`, `file.infected`, `file.scan_failed`); the transaction locks the payment, inserts the refund,
moves the payment to `refunded`, voids the receipt, writes an admin event and the audits (`refund.recorded`,
`payment.refunded`, `receipt.voided`, reason attached) and dispatches `RefundRecorded` (PAY-04) and `PaymentRefunded`
(for the accredited and Kit listeners, not built yet). No gateway refund call exists.

### Files foundation
`stored_files`: purpose (`refund_evidence | apple_pay_domain_association | receipt_pdf | finance_export | assessment_evidence |
evidence_bundle | cms_image | kpi_image | branding_logo | certificate_pdf | entity_logo`), private disk
(`FILES_DISK`), random path `files/<purpose>/YYYY/MM/<uuid>.<ext>`, sanitized original name, server-detected MIME,
size, SHA-256, NDMO class, owner (`attachable`), uploader, scan status `pending | clean | infected | failed`. `FileIntake`
checks extension, detected MIME and magic bytes per purpose, stores the file `pending`, scans it through
`Contracts\Files\FileScanner` and removes it unless it is clean. `ClamAvFileScanner` speaks clamd `INSTREAM` over
`FILES_CLAMAV_SOCKET` (no package) and fails closed; `FakeFileScanner` for tests. Downloads: the owning endpoint checks
its Policy and returns `{ url, expires_at, file_name }`; the URL is a temporary signed route valid 5 minutes, bound to
the requesting user, reusable in the window; `GET /files/{file}/download` checks the signature (403
`signature.invalid`, 410 `signature.expired`), the session user, the Policy of the owner again and the scan status
(409 `file.not_clean`), and audits every class-C download (`file.downloaded`). The Apple Pay domain association file is
now a scanned `stored_files` row (purpose `apple_pay_domain_association`); the public `.well-known` route still serves it
byte for byte.

### File housekeeping
- Uploads that need a clean verdict before the business step (refund evidence, the Apple Pay file) keep scanning
  synchronously in the request and fail closed (422 `file.scan_failed` when clamd is down; the file is removed).
  An upload found infected there answers 422 `file.infected`, is removed, is audited as `file.infected_upload_refused`
  on its (soft-deleted) stored-file row with `{ purpose, size_bytes }` only (never the file name, path or contents;
  the uploader is the audit actor) and raises INT-05 in-app to the active System Administrators naming the purpose only,
  at most once per purpose per `moashir.integrations.alert_cooldown_minutes` (10 minutes, like the scan alerts); every
  refusal inside that window is still audited. A failed scan raises neither.
  A row can still be left `pending` when a request is interrupted between storing and scanning (worker killed, timeout,
  an exception before the verdict), and later uploads that are accepted as `pending` (evidence, build step 6) hand
  their file to the same job: `ScanStoredFileJob::dispatch($storedFileId)`.
- `ScanStoredFileJob` (queued, unique per file for 3 hours, 6 attempts, timeout 120 s): reads the file and asks the
  `FileScanner`. Scanner down (verdict `failed`) → the file stays `pending` and the job is released after 60, 300, 900,
  1800 and 3600 seconds; after the sixth attempt the job fails, the file is marked `failed` (engine `scan-abandoned`,
  never served) and INT-04 goes to the System Administrators (in-app and e-mail, at most once per 10 minutes).
  Clean → `clean`, which makes the file available to its owner's download flow. Infected → `infected`, the binary is
  moved to `quarantine/<public_id>` on the same private disk (no extension), `quarantined_at` is set and INT-05 goes
  in-app to the System Administrators (purpose and file id only, never the file name); `StoredFileQuarantined` is raised
  for the owning domain (evidence: EVD-02 to the entity, see "Evidence files"). Audits: `file.scanned` (verdict,
  engine, signature) and `file.quarantined`.
- Idempotent: the verdict is written with a conditional update from `pending` (a verdict recorded by another process is
  never overwritten), the quarantine is claimed with `quarantined_at IS NULL` and its target path is derived from the file
  id, so a retry completes an interrupted quarantine; a second run scans, moves and alerts nothing.
- `moashir:files:scan-pending` (scheduled every 5 minutes, without overlapping) queues the job for every file still
  `pending` 10 minutes after it was stored (`moashir.files.scan_retry_after_minutes`; longer than a request, so it never
  races an in-request scan). The unique lock keeps one job per file.
- Orphan purge: `moashir:files:purge-orphans` (scheduled daily at 03:00, without overlapping) removes, binary and row
  (`forceDelete`), every stored file that was never attached to an owner (`attachable_type IS NULL`) and was created more
  than `FILES_ORPHAN_GRACE_HOURS` ago (default 24 hours). No flow keeps a file unattached beyond its own request (refund
  evidence is attached in the refund transaction, receipts and exports when they are generated), so a day is a safe
  margin while keeping unneeded class-C copies short-lived (PDPL minimization). Soft-deleted orphans and quarantined
  orphans are included; the Apple Pay association file is ownerless by design and never purged. Each run inserts a
  `housekeeping_runs` row (`task = orphan_file_purge`, `counts { purged_files, purged_bytes, by_purpose, grace_hours }`,
  append-only) and, when something was purged, a `file.orphans_purged` audit on that row with the counts only (no
  name, path or id).

### Local-only fakes
`FILES_SCANNER=fake`, `PDF_RENDERER=fake` and `SMS_PROVIDER=fake` select `FakeFileScanner`, `FakePdfRenderer` and
`FakeSmsGateway`, but only when `APP_ENV` is `local` or `testing` (`Services\Platform\LocalFakeAdapters`). In staging,
production or any other environment the flag is refused: the real adapters are bound (ClamAV, which fails closed without
clamd, the Chromium renderer when `PDF_CHROME_BINARY` is usable and `UnavailablePdfRenderer` otherwise, and
`SettingsSmsGateway`, so SMS is available only when the SMS settings are configured)
and `platform.fake_adapter_refused` is logged as critical with the setting name (`moashir.files.scanner`,
`moashir.documents.pdf_renderer`, `moashir.sms.driver`). The SMS decision is taken once per process
(`Data\Integrations\SmsDriverSelection`, a singleton shared by the gateway binding and `ConfiguredSmsAvailability`), so a
refused flag logs one line per worker, not one per sign-in. Set the flags in the local `.env` only.

### Payments and finance endpoints
| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/payments/mine` (`filter[status]` = comma list of statuses, `filter[purpose]`, pagination, `meta.stats` live only on the filtered set, `meta.status_counts` every status of the purpose filter incl. test payments, rows add `request`) | entity + `payments.own.view` |
| POST | `/payments` (`Idempotency-Key`) | entity + `payments.own.create` + the purpose permission, `throttle:sensitive` |
| GET | `/payments/{payment}`, `/payments/{payment}/receipt` | own entity (404 otherwise) |
| POST | `/payments/{payment}/verify` | own entity + `payments.own.create`, `throttle:payment-verify` (30/min) |
| POST | `/payments/{payment}/receipt/download` | own entity, `throttle:downloads` (30/min) |
| POST | `/webhooks/moyasar` | public, secret token, `throttle:webhooks` |
| GET | `/content/prices` | public, `throttle:public` (see "Pricing") |
| GET | `/finance/summary?from&to`, `/finance/transactions`, `/finance/transactions/{payment}` | `finance.operations.view` |
| POST | `/finance/transactions/export` | `finance.operations.export`, `throttle:exports` |
| GET / POST | `/finance/exports/{export}`, `/finance/exports/{export}/download` | the requester with `finance.operations.export` |
| POST | `/finance/payments/{payment}/refunds` | `finance.refunds.manage`, `throttle:sensitive` |
| GET | `/finance/refunds`, `/finance/refunds/{refund}`; POST `/finance/refunds/{refund}/evidence/download` | `finance.refunds.manage` or `finance.operations.view` |
| POST | `/finance/receipts/{receipt}/download` | `finance.operations.view` (or the owning entity) |
| POST | `/finance/receipts/{receipt}/reissue` | `finance.receipts.manage`, `throttle:sensitive` |
| POST | `/finance/payments/{payment}/manual-settle` | Super Admin only, reason, SEC-04 |
| GET | `/finance/payment-events` | `settings.payment.manage` (System Administrator) |
| GET | `/messaging/abandoned-carts` | `messaging.campaigns.send` |
| GET | `/files/{file}/download` (signed) | signed in + owner Policy |
| GET | `/settings/finance` | holder of `settings.assessment.manage` or `bag.admin.manage` |
| PUT | `/settings/finance` | the permission of every field sent (see "Finance settings"), `throttle:sensitive` |

Request and response shapes are listed in `agents/tasks/reports/Task-18-report.md`.

### Linked request on the staff payment records (Task-75)
`GET /finance/transactions/{payment}`, `GET /finance/bank-transfers/{payment}` and the approve / reject / manual-settle
answers carry `linked_request { number, path, path_label, status, progress{ subs_complete, subs_total } | null } | null`
(`null` until the accredited request is opened; the list rows do not carry it). Finance sees the number, status and progress
only: no request content and no new permission. `linked_request.id` is added only when the viewer may open the request
(`viewRequest`: `requests.queue.view` on a followed path, or the Super Admin). The entity link uses the existing `entity.id`
and is shown by the SPA to holders of `entities.view` only.

### Events and notifications
Events (after commit): `PaymentCreated` (PAY-01, entity, in-app), `PaymentPaid` (PAY-02, entity + Request Followers +
System Administrators; the staff link follows the audience (DF-2): holders of `finance.operations.view` open
`/finance/transactions/{id}`, other holders of `requests.queue.view` open the request `/admin/requests/{id}` once it is
opened, or the preparing tab `/admin/requests?path=accredited&stage=preparing`, and a recipient holding neither gets no
link), `PaymentFailed` (PAY-03, entity + System Administrators), `PaymentCancelled` (PAY-07 on expiry,
entity, in-app), `ReceiptIssued` (PAY-06, entity + Finance Officers for live receipts; queues the PDF), `RefundRecorded`
(PAY-04, entity + System Administrators + Finance Officers, never the recorder), `PaymentRefunded`. New codes: PAY-08
(payment to refund outside the platform), PAY-09 (refund reported by the gateway), FIN-01 (export ready). INT-04 covers
rejected verifications, unexpected statuses and unmatched live webhooks (once per 10 minutes).

### Deployment notes (payments)
- A queue worker (Horizon) must run: webhooks, receipt PDFs and large exports are queued. The scheduler must run every
  minute (`ExpirePaymentsJob` every minute, `ReconcilePendingPaymentsJob` every 5 minutes).
- ClamAV: install `clamav-daemon` on the server (in the Kingdom), keep its signatures updated (`freshclam`), let the
  PHP-FPM user reach the socket and set `FILES_CLAMAV_SOCKET`; set `StreamMaxLength` at least to `files.max_size_mb`.
  Without clamd every upload answers 422 `file.scan_failed` (fail closed). Never set `FILES_SCANNER=fake` outside local.
- Upload limits: PHP `upload_max_filesize` and `post_max_size` and Nginx `client_max_body_size` at least
  `files.max_size_mb` (20 MB default) for `/api/v1/finance/payments/*/refunds`; the webhook path stays at 256 KB.
- The webhook URL `<APP_URL>/api/v1/webhooks/moyasar` is inside `/api`, which Nginx already proxies.
- SPA CSP for the Moyasar form: derive the exact origins in staging (Q-110e); expected at least `script-src` and
  `style-src https://cdn.moyasar.com`, `connect-src https://api.moyasar.com`, `frame-src` / `form-action` for the 3-D
  Secure pages, and Apple Pay; pin the Moyasar.js version (≥ 2.3.0 for intents) with SRI.
- Set the service prices with `PUT /api/v1/settings/finance` (System Administrator); `PDF_RENDERER=chromium` needs the
  headless shell of the deployment kit (`provision.sh pdf-renderer`). Run `database/sql/production-roles.sql` again for the new grants (refunds and payment events
  INSERT/SELECT only, payments, receipts and exports without DELETE).

## Public site CMS, branding and KPIs (build steps 10–11)

### Data
- `cms_pages`: one row per code-defined page (`CmsPageKey`: `home`, `accredited_intro`, `advanced_intro`, `bag_details`,
  `lenses`, `kpis`, and since Task-49 `accessibility`), inserted by the migrations. Draft title and SEO text, `is_published`, the frozen
  `published_content` snapshot, `published_at` / `published_by_user_id`, `has_draft_changes`, `content_updated_at`.
- `cms_sections` (per page, `CmsSectionType`, unique `key` per page among live rows, `sort_order`, toggle) and
  `cms_section_items` (per section, `sort_order`, toggle). Both: localized `title`, `subtitle`, `description`,
  `cta_label` (`{ar, en}`, one language may be empty), `cta_url`, Font Awesome `icon`, `image_file_id`, free `meta`
  object (≤ 16 KB, 5 levels); soft deletes.
- `site_settings` (single row): social links, contact, footer note, quick links, site SEO, `featured_kpi_limit` (3..6,
  default 6), `show_featured_kpis`.
- `branding_themes` (single row): `accent_token` (`AccentToken`, CHECK), `logo_light_file_id`, `logo_dark_file_id`.
- `kpi_categories` (soft deletes, unique live `key`), `kpi_types` (Q-40, deactivate instead of delete when used),
  `kpis` (numbers as `numeric(18,4)` handled as strings by `KpiNumber`, status, colour tokens, `show_on_homepage`,
  `is_published`, `sort_order`, `value_updated_at`; archive = soft delete) and `kpi_values` (one row per KPI and
  measurement date).
- `stored_files` purposes `cms_image`, `kpi_image`, `branding_logo`: visibility `public_asset`, NDMO class `P`, random
  path, extension + detected MIME + magic bytes, synchronous malware scan (fail closed), SVG logos only without
  scripts, event handlers, `foreignObject`, entities or external references, PNG logos at most 4096 px a side.

### Draft and published content (CMS-04 / CMS-05)
Every section, item, page-detail change edits the draft and sets `has_draft_changes`. `POST
/admin/content/pages/{key}/publish` freezes the enabled sections and enabled items into `published_content` (missing
translations filled from the other language), clears the flag, removes page images no longer referenced by the draft
and raises CMS-04 in-app to the Content Officers and System Administrators (never the actor). `unpublish` raises CMS-05.
Site settings are not drafted: a change is public at once and raises CMS-04. Public reads use the snapshot only.

### Public reads, cache and maintenance (Q-96)
`/content/status`, `/content/home`, `/content/landing`, `/content/pages/{key}` read the published snapshot through
`PublicContentCache` (`cms:public:page:<key>` and `cms:public:site-settings`, TTL `moashir.cms.public_cache_seconds`
= 3600 s). The `ForgetPublicContent` listener forgets the page key after a publish / unpublish commits and the site
settings key after a settings change. While `home` is not published, `/content/home` and `/content/landing` answer
200 `{ published: false, maintenance: true }` and `/content/status` `{ maintenance: true }`; the SPA shows the
maintenance page on public routes. Sign-in, `/auth/*`, `/sanctum/*` and every back-office route stay available.
Public localized fields are always `{ ar, en }` objects with the empty language filled from the other one; the editor
endpoints return the stored values unchanged. `/content/pages/{key}` also returns `published_at` and `updated_at` (the last
draft edit included in the published snapshot; equal to `published_at` for pages published before Task-49), and every
`rich_text` section carries `blocks { ar: Block[], en: Block[] }` parsed from its `description` (format in "Legal texts
and pages"). A section or item `description` with HTML markup is refused (422), control and direction-override characters
are removed before it is stored.

### Public images
`GET /api/v1/content/files/{uuid}` streams a clean `cms_image`, `kpi_image` or `branding_logo` file (no session, no JSON
`Accept` needed, `throttle:public-assets` 600/min per IP) with its stored MIME type, `Content-Disposition: inline;
filename=image.<ext>`, `nosniff` and the API CSP (`default-src 'none'`), so an SVG opened directly runs nothing. Private
files, unscanned or infected files and unknown ids answer 404. Uploads (`POST /admin/content/images`,
`POST /admin/kpis/images`) return `{ file_id, url, mime_type, size_bytes }`; the editor then sends `image_file_id` (or
`null` to remove) with the section, item or KPI. A file attached to other content is refused (422 `file.not_usable`);
uploads never attached are removed by the orphan purge after `FILES_ORPHAN_GRACE_HOURS`.

### Branding (Q-42, Q-43, Q-44b, Q-45, Q-74)
One theme for every back-office user. The controlled palette is `AccentToken` (brand default + 8 colours) with the
designer's colours in `config/moashir.php` (`branding.palette`, `branding.surfaces`). Every change is checked for
WCAG 2.1 AA (4.5:1) on four pairs — accent text on the light surface, dark text on the accent (light and dark mode),
accent on the dark surface — and refused with 422 `contrast_fail` (`meta.pairs[]`). `settings.branding.manage` is
hard-locked to the System Administrator (Super Admin by bypass, reason 10..1000 required, SEC-04). Audit
`settings.branding.updated` with before / after and the tag `accent`, `logo_light`, `logo_dark` or `reset`. The public
site keeps the official identity (Q-43).

Logos (SEC-22, Task-51): PNG only, up to `branding.logo_max_kb` and `branding.logo_max_side_px`. SVG is refused (422
`validation_failed` on `file`; SVG markup renamed `.png` fails the content check) because a deny-list cannot make active
SVG content safe. The upload is checked by extension, detected type and magic bytes, scanned by ClamAV, then decoded and
re-encoded by GD (`Services\Settings\BrandingLogoIntake`, transparency kept), so only freshly encoded pixels are stored
and served; text chunks and any trailing data are dropped. Public images are served only as `image/png` /
`image/jpeg`, so an SVG stored before this change is no longer served.

### KPIs
Public list filters (Q-40): `filter[category_id]`, `filter[status]`, `filter[measurement_year]`, `filter[kpi_type_id]`,
`filter[owner]` (text), `search` (name, Arabic-normalized), `sort` ∈ `order` (default), `name`, `-name`, `-created_at`,
`created_at`, `-value`, `value`, `-last_update`, `last_update`; `page`, `per_page` ≤ 100 (default 12). Featured:
published ∧ `show_on_homepage`, at most `featured_kpi_limit`; at most 6 KPIs may be flagged (422
`kpi.featured_limit_reached`). Reorder: arrows (`direction: up | down`) and drag-and-drop (`ordered_ids`, complete
permutation, 422 `order.incomplete`) under one advisory lock. A changed value writes today's `kpi_values` row (Asia/Riyadh
date) and raises CMS-02; dated measurements move the current value only when they are the latest. Notifications:
CMS-01 created, CMS-02 value, CMS-03 published, CMS-05 unpublished or archived (in-app only, no SMS).

### Content and KPI endpoints
| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/content/status`, `/content/home`, `/content/landing`, `/content/pages/{key}`, `/content/legal/{terms\|privacy}` | public, `throttle:public` |
| GET | `/content/files/{uuid}` | public, `throttle:public-assets` |
| GET | `/admin/content/home`, `/admin/content/pages`, `/admin/content/pages/{key}`, `/admin/content/site-settings` | `content.home.manage` |
| PUT | `/admin/content/pages/{key}` | `content.home.manage`, `throttle:content-write` |
| POST | `/admin/content/pages/{key}/publish`, `/admin/content/pages/{key}/unpublish` | `content.home.manage`, `throttle:sensitive` |
| POST | `/admin/content/pages/{key}/sections` | `content.home.manage`, `throttle:content-write` |
| PATCH | `/admin/content/sections/reorder`, `/admin/content/sections/{id}`, `/admin/content/sections/{id}/enabled` | `content.home.manage`, `throttle:content-write` |
| DELETE | `/admin/content/sections/{id}` | `content.home.manage`, `throttle:content-write` |
| POST / PATCH | `/admin/content/sections/{id}/items`, `/admin/content/sections/{id}/items/reorder` | `content.home.manage`, `throttle:content-write` |
| PUT / PATCH / DELETE | `/admin/content/sections/{id}/items/{item}`, `…/items/{item}/enabled` | `content.home.manage`, `throttle:content-write` |
| PATCH | `/admin/content/site-settings` | `content.home.manage`, `throttle:content-write` |
| POST | `/admin/content/images` | `content.home.manage`, `throttle:uploads` |
| GET / POST | `/admin/content/legal/{terms\|privacy}/versions` | `content.home.manage` (POST `throttle:content-write`) |
| GET / PUT / DELETE | `/admin/content/legal/versions/{id}` | `content.home.manage` (PUT / DELETE `throttle:content-write`) |
| POST | `/admin/content/legal/versions/{id}/publish` | `content.home.manage`, `throttle:sensitive` |
| GET | `/settings/branding` | any signed-in user (palette only with `settings.branding.manage`) |
| PUT | `/settings/branding`; POST `/settings/branding/reset` | `settings.branding.manage`, `throttle:sensitive` |
| POST | `/settings/branding/logo` | `settings.branding.manage`, `throttle:uploads` |
| GET | `/kpis`, `/kpis/featured`, `/kpis/{slug}`, `/kpi-categories`, `/kpi-types` | public, `throttle:public` |
| GET / POST | `/admin/kpis`; PATCH `/admin/kpis/reorder`; POST `/admin/kpis/images` | `content.kpis.manage` |
| GET / PUT / DELETE | `/admin/kpis/{id}` | `content.kpis.manage` |
| PATCH | `/admin/kpis/{id}/published`, `/admin/kpis/{id}/reorder` | `content.kpis.manage` |
| GET / POST | `/admin/kpis/{id}/values` | `content.kpis.manage` |
| GET / POST / PUT / DELETE | `/admin/kpi-categories(/{id})`, `/admin/kpi-types(/{id})` | `content.kpis.manage` |

Request and response shapes: `agents/tasks/reports/Task-33-report.md`.

### Demo content
`moashir:demo:install` now also loads `database/data/demo/public-content.json` (fictional bilingual content): the six
pages published, site settings, 8 KPI categories, 3 KPI types and 8 published KPIs (6 on the homepage). Since Task-42 the
home page follows the approved home page brief v2: hero (`meta.eyebrow`, `meta.highlight`, illustrative `meta.preview`,
items `meta.role = secondary-cta | point`), platform tour (`how_it_works`), paths (advanced = `coming_soon`, no button),
model, journey (`how_it_works` + `meta.variant = journey`, 6 stages), Quick Excellence Kit (`coming_soon`, no purchase
button), call-to-action banner (`rich_text` + `meta.variant = cta`, secondary sign-in item), leaders teaser and contact;
every section carries `meta.eyebrow {ar,en}`; no `demo` availability remains; the KPI promo, lenses and assistant
sections are no longer part of the demo home page (KPIs only in the stats strip, assistant hidden in phase 1). On a demo
installed before this change, `php artisan moashir:demo:refresh-home` (needs `APP_DEMO_DATA=true` and an installed demo,
refusals logged like the install command) soft-deletes the current home sections and items, installs the home page of the
demo file and publishes it; the other pages, the site settings and the KPIs are untouched. On a demo
installed before this change run `php artisan moashir:demo:install --content-only` (needs `APP_DEMO_DATA=true` and an
installed demo; pages that already have sections, filled site settings and existing KPIs are kept; refusals logged as
before). Since Task-49 the same install (and `--content-only`) also publishes the legal drafts as the current terms and privacy
versions and publishes the `accessibility` page (section "Legal texts and pages").

### Deployment notes (content)
- Migrations `2026_11_02_130000` … `130300` (file purposes, CMS tables with the six page rows and the site settings row,
  the branding row, KPI tables). No grant change (default privileges cover the new tables). No new permission key and no
  environment variable.
- Nginx `client_max_body_size` must allow `moashir.cms.image_max_kb` (5 MB) on `/api/v1/admin/content/images` and
  `/api/v1/admin/kpis/images`.

## Legal texts and pages (Task-49)

Operator decision 2026-11-04: the team prepared drafts of the terms of use, the privacy policy and the accessibility statement
(`agents/tasks/reports/legal-drafts/`, copied unchanged into `database/data/legal/*.{ar,en}.md`); the Content Officer edits
them; the client publishes after legal review.

### One source of truth for consent texts
- The terms of use and the privacy policy live ONLY in `terms_versions` (kinds `terms` / `privacy`). Registration
  (`terms_version_id`, `privacy_version_id`), staff invitations and the contact form (`privacy_terms_version_id`) keep
  referencing the CURRENT version, so a recorded consent always points to the exact text that was shown. They are not CMS
  pages.
- `terms_versions` gained `title` (`{ar, en}`, nullable for older rows: the public read then uses `lang/*/legal.php`
  `titles.*`) and `published_at`. A row with `published_at` null is a DRAFT (`effective_from` and `published_by_user_id`
  null too, CHECK `terms_versions_published_check`); at most one draft per kind (partial unique index
  `terms_versions_one_draft`); `is_current` requires a published row. A published version is IMMUTABLE: the trigger
  `moashir_guard_terms_versions()` refuses any change of `kind`, `version`, `title`, `body`, `effective_from`,
  `published_at`, `published_by_user_id` and any DELETE of a published row (only `is_current` may change, when a newer
  version is published). Changing a text = a new draft = a new version. Status in the API: `draft | current | superseded`.
- Publishing (`Services\Identity\TermsVersionPublisher`, used by the API, the console command and the demo) takes the
  advisory lock `terms:<kind>`, marks the previous current version superseded, sets `published_at`, `effective_from`
  (Riyadh date of the publication for the API) and the publisher, audits `terms.published` (previous / new label, title,
  SHA-256 and length of each language) and forgets the cached public copy after commit.
- The accessibility statement is not part of consent: it is the plain CMS page `accessibility` (managed by the existing
  CMS editor, served by `GET /content/pages/accessibility`, 404 `cms.page_not_published` until published).

### Rich text format (no HTML path)
Legal bodies and CMS `rich_text` descriptions are plain text in a small Markdown subset: `#`, `##`, `###` headings,
paragraphs (consecutive lines are joined), `- ` / `* ` bullet lists, `1. ` numbered lists (the first number is kept as
`start`), pipe tables (`| a | b |`, an optional `|---|---|` line makes the first row the header). Nothing else is
interpreted: links, emphasis, images and `[[...]]` placeholders stay literal text. Input with an HTML tag or comment is
refused with 422 (`Rules\FreeOfHtmlMarkup`, message `validation.custom.rich_text.markup`); control characters, BOM and
bidirectional override / isolate characters are removed and line endings normalised before storage
(`Support\RichText\RichTextSanitizer`). Public reads return parsed blocks (`Support\RichText\RichTextParser`), so the SPA
renders them with text interpolation only:

```
{ "type": "heading", "level": 2, "text": "1. Introduction and scope" }
{ "type": "paragraph", "text": "This policy explains ..." }
{ "type": "list", "ordered": false, "start": null, "items": ["visitors of the platform;", "..."] }
{ "type": "list", "ordered": true, "start": 1, "items": ["...", "..."] }
{ "type": "table", "header": ["Data category", "Retention period"], "rows": [["...", "..."]] }
```

### Endpoints
| Method | Path | Ability / limiter | Body / answer |
|---|---|---|---|
| GET | `/content/legal/{terms\|privacy}` | public, `throttle:public` (cached, `moashir.cms.public_cache_seconds`) | `{ id, kind, version, effective_from, published_at, title{ar,en}, body{ ar: Block[], en: Block[] } }` of the CURRENT version (both languages always, whatever `Accept-Language`); 404 `legal.not_published` when none is published (drafts are never shown), 404 for another kind. `id` is the `terms_version_id` / `privacy_version_id` the forms send. |
| GET | `/admin/content/legal/{kind}/versions` (`page`, `per_page` ≤ 100) | `content.home.manage` | paginated `LegalVersionSummary` = `{ id, kind, version, status, title, effective_from, published_at, created_at, updated_at }`, draft first, then newest publication |
| POST | `/admin/content/legal/{kind}/versions` | `content.home.manage`, `throttle:content-write` | `{ version (^[A-Za-z0-9._-]{1,20}$), title{ar,en} (≤ 200 each, single line), body{ar,en} (≤ 60000 each) }` → 201 `LegalVersion` (= summary + `body{ar,en}` source text); 409 `legal.draft_exists`; 422 `legal.version_taken`, `validation_failed` |
| GET | `/admin/content/legal/versions/{id}` | `content.home.manage` | `LegalVersion` |
| PUT | `/admin/content/legal/versions/{id}` | `content.home.manage`, `throttle:content-write` | partial `{ version?, title?, body? }` (`id`, `kind`, `status`, `is_current`, `effective_from`, `published_at` prohibited) → `LegalVersion`; 409 `legal.version_not_draft` (`errors.version_status`) on a published version |
| DELETE | `/admin/content/legal/versions/{id}` | `content.home.manage`, `throttle:content-write` | 204 (draft discarded, audited); 409 `legal.version_not_draft` |
| POST | `/admin/content/legal/versions/{id}/publish` | `content.home.manage`, `throttle:sensitive` | `{ reason? (10..1000) }` → `LegalVersion` with `status: current`; the Super Admin must give a reason (422 `reason_required`, SEC-04 raised, reason stored in the audit); 409 `legal.version_not_draft` |

Same permission as the CMS (`content.home.manage`: Content Officer and System Administrator by default; Super Admin by
bypass); every other role 403, guests 401. Audit events: `terms.draft_created`, `terms.draft_updated`,
`terms.draft_discarded`, `terms.published` (label, title, SHA-256 and character count of each language, never the text).

### Seeding
- Every install (reference data, idempotent): `php artisan moashir:legal:install-drafts` stores the terms and privacy
  drafts as UNPUBLISHED versions labelled `2026-11-draft` and fills the `accessibility` page with 13 `rich_text` sections
  (`intro` = the draft line, `section-1` … `section-12` = the `##` sections) WITHOUT publishing it. A kind that already has
  a draft (or the seed label) and a page that already has sections are left alone; current published versions are never
  touched. The first line «مسودة للمراجعة القانونية …», every `[[...]]` placeholder and the conditional AI section of the
  privacy policy (§17) are kept: the client fills or removes them and publishes after legal review.
- Demo (`moashir:demo:install`, and `moashir:demo:install --content-only` on an installed demo): the seed drafts are
  PUBLISHED as the current terms and privacy versions (the stored draft is published in place, or a new version is
  created and published when none is stored; an earlier current version such as `demo-1` is kept as superseded) and the
  accessibility page is filled when empty and published. A second run changes nothing; a seed version that was already
  published is never published again.

## Notification centre, dashboards and Action Center (build step 13)

### Notification centre
In-app notifications are the `notifications` rows written by `Notifications\Channels\InAppChannel` (bilingual `title` /
`body` rendered at send time, `code`, `category`, SPA `link`). Every query is scoped to the signed-in user
(`Actions\Notifications\FindOwnNotification`), so another user's notification answers 404 for everyone, the Super Admin
included. `archived_at` (new column) moves a notification out of the inbox; it is never deleted by the user. The permanent
deletion of an entity deletes the owner's notifications and notification preferences.

- `GET /notifications` — inbox (not archived) by default, newest first; `filter[category]` (`action_required |
  informational | alert | error`), `filter[read]` (`true | false`), `filter[archived]=true` (archive instead of inbox),
  `filter[code]` (`SELF-03` …), `search` (≥ 2 searchable characters, Arabic-normalized, title and body in both languages),
  `filter[path]` (`self | accredited | advanced`), `page`, `per_page` 1..100 (default 15). Item `{ id (uuid), code, module,
  category, title{ar,en}, body{ar,en}, link, path | null, path_label{ar,en} | null, is_read, read_at, actioned_at, archived_at,
  created_at }`; `meta.pagination` + `meta.unread_count`. `path` is stored on every request, assessment, award and accredited
  payment notice.
- `GET /notifications/unread-count` → `{ count, by_category{ action_required, informational, alert, error } }` (inbox only).
- `PATCH /notifications/{id}/read` and `PATCH /notifications/{id}/archive` → the item (idempotent: the first time is kept).
- `POST /notifications/read-all { category? }` → `{ updated }` (inbox only).

### Notification types and preferences
The catalogue of every `NotificationCode` is code-defined in `Support\Notifications\NotificationTypeDefinitions` (one row per
code: channels `in_app | email | sms` as built, audiences, mandatory flag), read through `Services\Notifications\
NotificationTypeCatalog`; a unit test keeps it complete. Audiences are the role keys plus `committee_assessor` (assessor role),
`super_admin`, `account_holder` (the account concerned, e.g. USR-01) and `contact_handler` (any holder of
`contact.messages.manage`, whatever the role, Task-42). Labels and descriptions come from the SPA i18n by code
(Q-102).

- In-app delivery can never be turned off. A user may turn off `email` / `sms` of a type that reaches one of their
  audiences and is not mandatory. Mandatory (locked) types: every SEC, USR, PAY and INT code, AI-05, REG-02 (one-time code),
  REG-05 … REG-10 (account state, verification, official e-mail), ACC-07 / ACC-12 / ADV-25 / ADV-27 (deadline with a
  consequence) and ADV-31 (withdrawal).
- `notification_preferences` stores one row per user, code and channel (`is_enabled`; no row = default on).
  `Listeners\Notifications\SkipDisabledDeliveryChannel` answers the framework's `NotificationSending` event with `false`
  for a disabled, configurable channel, so the channel is skipped (`NotificationSkipped`) at send time, also in queued
  notifications. It applies to notifications implementing `Notifications\Contracts\HasNotificationCode`
  (`EntityNotification`, which carries every business notice with e-mail).
- `GET /me/notification-preferences` → `[{ code, module, category, is_mandatory, channels[{ channel, enabled, configurable }] }]`
  for the types the user receives. `PUT /me/notification-preferences { preferences: [{ code, channel: email|sms, enabled }] }`
  (1..200 rows, last row wins for a repeated pair) answers the same list; 422 `notification_preference.locked`
  (`errors["preferences.<i>.channel"]`) or `notification_preference.not_applicable` (`errors["preferences.<i>.code"]`), nothing
  is stored when any row is refused.
- `GET /settings/notifications/types` (`settings.notifications.manage`, System Administrator) → read-only catalogue
  `[{ code, module, category, channels[], audiences[], is_mandatory, user_configurable_channels[] }]`, `meta.total`;
  `filter[module|category|audience]`. Editing templates and the system-wide channel switches of 03 §14.2 are not built yet.

### Dashboards
`GET /dashboard/summary?board=<board>` (`dashboard.view`) resolves the boards a user holds: one per role
(`system_admin`, `award_secretary`, `filtering_officer`, `request_follower`, `assessor`, `content_officer`, `criteria_officer`,
`finance_officer`, `entity`), `committee_assessor` while the user has an active committee seat in a cycle that is not closed,
and `executive` for holders of `dashboard.executive.view` (Q-27). The Super Admin holds `system_admin` and `executive`. The
landing board follows 03 §17 (System Administrator > Award Secretary > Filtering Officer > Follower > Assessor > Content >
Criteria > Finance > Entity), except that an assessor with an active seat lands on `committee_assessor` while a cycle of that
seat is in the committee stage (Q-52). One workspace, no role switch (Q-42): `available_boards` lists every held board.
403 `board.not_held` for a board the user does not hold, 422 `validation_failed` (`errors.board`) for an unknown key.

```
{ "board": "request_follower", "landing_board": "request_follower", "available_boards": ["request_follower", "assessor"],
  "cards": [{ "key": "awaiting_assignment", "value": 3, "format": "count", "link": "/admin/requests?status=submitted%2Cawaiting_review_assignment" }],
  "widgets": [{ "key": "pipeline", "data": [{ "path": "accredited", "status": "submitted", "count": 3 }] }],
  "action_center": [{ "key": "assign_requests", "count": 3, "severity": "warning", "link": "…", "due_at": null, "context": null }],
  "generated_at": "2026-11-03T08:00:00+00:00" }
```

`format` ∈ `count | percent | money | days | date`; percentages and money are decimal strings (`"77.00"`, `"2300.00"`, live
payments only, never floats); a card without data has `value: null`. One query class per board
(`Services\Dashboards\Boards\*`, tagged `dashboards.boards`); each uses aggregate queries with a fixed query count (feature tests
compare the query count for small and larger data sets). Frozen requests (soft-deleted entities) are excluded.

| Board | Cards | Widgets | Scope |
|---|---|---|---|
| `system_admin` | entities total / active / pending verification, active staff, running cycles, self assessments, accredited and advanced requests, open, completed, overdue, payments collected / open / paid, refunds total | entities by region, sector, founding decade, governance band, level (`entities_by_level`); 10 lowest main criteria (accredited completed results); last 10 status changes | global |
| `executive` | entities total / active, completed self / accredited, advanced requests, accredited average %, cycles, payments collected (no links) | entities by region, sector, governance, level (`entities_by_level`); 10 lowest main criteria | global, read only |
| `request_follower` | awaiting assignment, assigned, in review, needs completion, completed, overdue, completion rate | requests by path, pipeline by path and status, 10 busiest assessors | submitted accredited and advanced requests |
| `assessor` | to start, in progress, awaiting entity, overdue, completed, average duration (days) | 5 due soon (own), 5 weakest main criteria of own completed results | own assignments |
| `committee_assessor` | assigned, completed, remaining, progress, own average | seats with cycle dates and counts, 5 latest rows (no entity data) | own seats |
| `filtering_officer` | awaiting filtering, qualified, not qualified, override decisions | member cycles | member cycles, filtering states only |
| `award_secretary` | applicants, qualified, not qualified, in committee, awaiting approval, results approved, winners, committee progress | member cycles, top 10 by committee average, score histogram, by region | member cycles |
| `entity` | latest result %, open requests, unread notifications, accepted evidence (`accepted_evidence_count`) | latest result (self or accredited), main criteria of the latest ELIGIBLE accredited result (`criteria_progress`, Task-67 N-1), 5 latest requests (masked statuses), payments card (Q-98) | own entity |
| `finance_officer` | revenue, VAT, paid, open, refunds total and count, receipts issued | daily paid payments (30 days, Asia/Riyadh), by service | live payments |
| `content_officer` | pages total / published / with draft changes, KPIs total / published / featured | 5 latest edits | global |
| `criteria_officer` | active mains / subs / points of the published version, open assessments on it, draft problems | published version, draft version | global |

Removed (no data source, Q-27): lens counts, support tickets, conversations, news / FAQ counts, satisfaction, maturity stage,
subscriptions, Kit stages, certificates, recommendations, re-assessment date. Payments card: `{ last_payment{ id, reference,
purpose, status, total, currency, created_at, paid_at } | null, pending_payment{ … , expires_at } | null, receipts_count, link }`.

### Action Center
Every summary carries `action_center` (also `GET /dashboard/action-center`, `meta.total`, `meta.generated_at` ISO 8601 UTC): the actionable items of ALL held
roles and permissions (`Services\Dashboards\ActionItems\*`, tagged `dashboards.action-items`, one or two queries each), only
items with a count, sorted danger → warning → info, then by due date. Keys: entity `complete_profile`, `submit_self_draft`,
`submit_accredited_draft`, `complete_accredited_request` / `complete_advanced_request` (danger, completion deadline),
`submit_advanced_draft` (submission end), `complete_payment` (order expiry); queue `assign_requests` (`requests.assign`),
`follow_up_overdue_requests`; assessor `start_reviews`, `continue_reviews`, `overdue_reviews`; committee
`complete_committee_evaluations`, `answer_committee_discussions`; `decide_filtering`; secretary `approve_committee_results`,
`approve_substitute_evaluations`; finance `record_refunds` (paid accredited payments that opened no request after 30 minutes,
the PAY-08 duplicate case); `review_entity_email_changes`, `handle_privacy_requests`, `fix_criteria_draft`,
`publish_content_changes`, `answer_contact_messages` (Task-42), `read_contact_replies` (any signed-in sender with unread
team replies, `info`, link `/messages/{uuid}` for one message or `/messages`, Task-46). Labels come from the SPA i18n by key.
Every item carries `context` (Task-46): `null` for staff items; for entity items and `read_contact_replies` an object with
the fixed nullable keys `{ assessment_id, payment_id, message_id, reference, remaining_subs, percent }`, filled only when
`count = 1` — `submit_self_draft` / `complete_accredited_request` → `assessment_id`, `reference`; `submit_accredited_draft` →
plus `remaining_subs` (the accredited progress); `complete_payment` → `payment_id` (uuid), `reference`;
`read_contact_replies` → `message_id`, `reference`; `complete_profile` → `percent` (the profile completion); the advanced
items keep every key `null`.

### Notification and dashboard endpoints

| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/notifications`, `/notifications/unread-count` | `notifications.view` (own only) |
| POST | `/notifications/read-all` | `notifications.view` |
| PATCH | `/notifications/{id}/read`, `/notifications/{id}/archive` | `notifications.view` + owner (404 otherwise) |
| GET / PUT | `/me/notification-preferences` | signed in; PUT `throttle:sensitive` |
| GET | `/settings/notifications/types` | `settings.notifications.manage` |
| GET | `/dashboard/summary`, `/dashboard/action-center` | `dashboard.view` (+ held board) |

Deployment: migrations `2026_11_02_140000` (`notifications.archived_at` + index) and `2026_11_02_140100`
(`notification_preferences`). No new permission key, package or environment variable.

## E-mail templates (Task-50)

Every e-mail is a notification built with `App\Notifications\Messages\BrandedMailMessage` (an arch test refuses a plain
`MailMessage` under `App\Notifications`). It renders through Laravel's Markdown mail pipeline with one branded layout:

- Template `resources/views/vendor/notifications/email.blade.php`; components `resources/views/vendor/mail/{html,text}/`
  (`message`, `paragraph`, `quote`, `code`, `action`); theme `resources/views/vendor/mail/html/themes/moashir.css`
  (inlined into every element) + `themes/adaptive.css` (mobile and dark-mode media queries, kept in `<style>`).
  `config/mail.php` sets `markdown.theme = moashir` and the component path. No Markdown parsing of the lines, so text
  such as `[x](url)` in an entity name stays plain text.
- `<html lang dir>`, the wrapper tables' `dir`, alignment and font stack follow the active locale (Arabic: RTL, right
  aligned, Tahoma / Segoe UI / Arial; English: LTR, left aligned, Montserrat / Arial). `App\Support\Mail\MailFrameComposer`
  (registered by `NotificationMailServiceProvider`) gives the template the frame: locale, direction, logo path,
  `/privacy` and `/terms` links on `FRONTEND_URL`, current year (Asia/Riyadh).
- Locale: users get their profile language (`HasLocalePreference`); guest contact senders get the language of their
  submission; other on-demand recipients get the locale the sender sets, otherwise `app.locale` (`ar`).
- Frame texts in `lang/{ar,en}/mail.php`: subject prefix «منصة مؤشر — …» / "Moashir — …", greeting by name when the
  recipient is a user («مرحباً سارة،» / "Hello Sara,") or «السلام عليكم،» / "Hello,", sign-off «مع التحية، فريق منصة مؤشر» /
  "Regards, The Moashir team", footer © year + privacy + terms + the reason line (`App\Enums\Notifications\MailReason`:
  account, invitation, address verification, linked address, contact form, security mailbox).
- Logo: `resources/mail/moashir-logo-on-light.png` (360 × 210, 6 KB, rendered from
  `resources/documents/logos/moashir-logo-on-light.svg` on an opaque white background so it stays readable in dark-mode
  clients), attached inline (`cid:`). No remote image, font, pixel or stylesheet; links point only to the platform domain.
- Codes (`->code()`) are shown large in an LTR isolate (`<bdi dir="ltr">`), selectable; staff replies (`->quote()`) in a
  quoted block with `dir="auto"`; actions as a button plus the raw link below it (`word-break: break-all`). Lines added
  after a code or a quote follow it.
- Every e-mail is multipart (HTML + plain text, the text part rendered from the same template with the `text` components).
- Quantities use `trans_choice` with `lang/{ar,en}/quantities.php` (six Arabic forms: 0, 1, 2, 3–10, 11–99, 100+, in the
  genitive/accusative form used after «لمدة» / «على» / «بعد»; two English forms). Notification texts receive the counted
  phrase (`LocalizedText::fromChoice('quantities.files', $n)`) in placeholders named after the noun (`:files`, `:winners`, …).
- SMS: the default OTP text uses the new `{validity}` placeholder («5 دقائق» / "5 minutes"); `{minutes}` (bare number)
  stays accepted for stored custom texts. The one-segment check uses the longest validity phrase of each language.

Preview (renders through the real mail channel on the `array` mailer; nothing is sent, fictional data only):

```
php artisan moashir:mail:preview [CODE] [--locale=ar] [--locale=en] --out=<existing directory>
```

Without a code it renders the main e-mails (`MailSamples::MAIN`: sign-in code, password reset, staff invitation,
registration, payment receipt, accredited result, certificate issued, contact reply); with a code (`REG-02`, `PAY-06`, …)
every variant of that code. Each sample gives `<name>.<locale>.html` (logo as a data URI, opens in a browser) and
`<name>.<locale>.txt` (subject + plain-text part).

## Path distinction (phase 1, operator decision Session 24)

One `assessor` role; each assessor is qualified for one or more paths; a Requests Follower can be limited to one or more paths;
every queue, count, notification and setting is per path. Codes: request paths `accredited | advanced` (`self` is never queued),
qualifications `accredited | advanced_initial` (enum `Enums\Identity\AssessorQualification`, extensible: a new case + its CHECK
value). Labels `path_label{ar,en}` come from `lang/*/assessments.php` (`paths.*`, `qualifications.*`).

### Assessor qualification
- Table `user_assessor_qualifications` (user, qualification, granted by; unique pair; CHECK on the values).
- `PUT /users/{id}/assessor-qualifications { qualifications: ["accredited", "advanced_initial"], reason? }` (`users.manage`,
  seeded to the System Administrator only) replaces the set (empty list = none) → `User`. 422 `assessor_qualification.not_assessor`
  (`errors.qualifications`) when a non-empty set targets an account without `assessment.score.manage` or an entity account,
  `sod_self_grant` for the caller himself, `validation_failed`; Super Admin needs `reason` (10..1000, `reason_required`) and raises
  SEC-04; Super Admin accounts answer 404. Audit `user.assessor_qualifications_changed` (old / new sorted lists, only when the set
  changes).
- `User` (`GET /users`, `/users/{id}` and every user write) adds `assessor_qualifications[]` and `request_scope { all_paths,
  paths[] }`; `GET /auth/session` adds the same two fields for the signed-in user (sidebar entries).
- Enforcement: `GET /requests/assessors`, assign and reassign accept only assessors qualified for the request's path (422
  `assessor.not_qualified`, `meta.required_qualification`, `meta.path`); cycle initial assessors need `advanced_initial` at linking
  (`PUT /award/cycles/{id}/initial-assessors`, 422 `assessor.not_qualified`), in the candidates list (`role=initial_assessor`) and
  again at assignment. A Super Admin reopen re-assigns the last assessor only while they are still qualified (otherwise the request
  goes back to `submitted`). Removing a qualification never ends an open assignment: the assessor finishes it, the follower may
  reassign. Committee seats (phase 2) need no qualification yet.
- Migration of existing data (`2026_11_03_100000`): every staff account that held `assessment.score.manage` (through a role or
  directly) is qualified for `accredited`; every account linked as an ACTIVE initial assessor of an award cycle is also qualified
  for `advanced_initial`, so running cycles keep working. New assessors start without qualifications: the System Administrator
  sets them after the invitation.

### Follower path scope
- Table `user_request_paths` (user, path, granted by; unique pair; CHECK `accredited | advanced`). No row = every path (the
  default for every existing follower); rows = only those paths. The Super Admin always follows every path.
- `PUT /users/{id}/request-scope { all_paths: true }` or `{ all_paths: false, paths: ["accredited"] }` (1..2 distinct paths),
  `reason?` → `User`. 422 `request_scope.not_follower` (`errors.paths`) when limiting an account without `requests.queue.view` or
  an entity account, `sod_self_grant`, `validation_failed`; Super Admin reason + SEC-04 as above; audit `user.request_paths_changed`
  (`["all"]` or the sorted paths).
- Enforced server-side (`Services\Identity\RequestPathScope`): queue (`filter[path]` limited to the followed paths, 422 otherwise;
  `filter[cycle_id]` refused without `advanced`), request detail, assign, reassign, return for completion and withdraw (404 for
  another path), the assessment record scope of the global readers (`GET /assessments`, `/assessments/{id}`, form, evidence,
  read-only review, global search → requests of other paths are invisible, except the user's own review work), the
  `request_follower` dashboard (cards, `requests_by_path`, pipeline, busiest assessors) and the `assign_requests` /
  `follow_up_overdue_requests` Action Center items, award cycles (the role-based global reader / committee manager status of the
  System Administrator and the Requests Follower needs `advanced` in the scope), and broadcast notifications: a staff notice that
  carries a path reaches only the role / permission holders who follow it (targeted notices to the assignee are unchanged).

### Separate queues and counts
- `GET /requests` and `GET /requests/assigned` serve one path each (`meta.path`) and return `meta.path_counts` for the sidebar
  (queue: `total`, `awaiting_assignment`, `overdue`; assigned: `new`, `in_progress`, `completed`), each entry with `path_label`.
- Notifications store `path` (`notifications.path`, nullable) for request, assessment, award and accredited-payment notices;
  the notification item returns `path` + `path_label`; `GET /notifications?filter[path]=` filters by it.
- `path_label{ar,en}` is added next to `path` on every request / assessment payload: queue row and detail, assigned row, assessment
  record, result, review context, global-search hits, deletion-impact open requests, dashboard rows (`due_soon`, entity
  `requests`, `latest_result`, `activity`, `pipeline`, `requests_by_path`).

### Per-path settings
- `GET /settings/assessment/paths/{accredited|advanced}` (`settings.assessment.manage`; `self` answers 404) → `{ path, path_label,
  review_sla_days, review_sla_limits{min,max}, upload_limits{ max_file_mb, allowed_types[], max_files_per_sub },
  upload_limit_bounds{ max_file_mb: 100, max_files_per_sub: 50 }, evidence_type_catalog[], is_paid, price{ price_before_vat,
  vat_rate, vat_amount, total, currency } | null, entity_texts{ intro, instructions, after_submission } }` (each text `{ar,en}` or
  null).
- `PUT` (partial): `review_sla_days` 1..120, `upload_max_file_mb` 1..100, `evidence_allowed_types[]` ⊂ catalog (≥ 1),
  `evidence_max_files_per_sub` 1..50, `price` (`"2300.00"`, before VAT; paid paths only — `prohibited` on `advanced`; also checked
  with `settings.finance.edit-field`), `entity_texts{ <key>: {ar, en} | null }` (both languages, ≤ 2000 characters each, a key
  left out is kept, `null` clears it), `reason` (Super Admin, SEC-04). One transaction under the settings locks; every changed key
  is audited (`settings.assessment_updated`; the price `settings.finance_updated`, same key as `/settings/finance`). A changed
  accredited SLA re-derives the open due dates (`RecomputeAccreditedDueDatesJob`); the advanced SLA is the default copied into new
  award cycles (each cycle keeps its own `initial_review_sla_days`).
- Storage: the accredited / advanced SLA and the accredited price keep their keys (`assessment.accredited_review_sla_days`,
  `assessment.advanced_initial_review_sla_days`, `assessment.accredited_price`); new keys
  `assessment.<path>_upload_max_file_mb`, `…_evidence_allowed_types`, `…_evidence_max_files_per_sub`, `…_entity_texts`. Migration
  `2026_11_03_100300` copies the stored shared values (`files.max_size_mb`, `files.evidence_allowed_types`,
  `files.evidence_max_files_per_sub`) into both paths, so nothing changes for existing data. `files.max_size_mb` stays the limit of
  non-evidence uploads (refund evidence).
- Entity-facing read: `GET /content/assessment-paths/{accredited|advanced}` (public, `throttle:public`) → `{ path, path_label,
  entity_texts, review_sla_days, upload_limits, is_paid, price }`. The entity form (`GET /assessments/{id}/form`) returns the
  `upload_limits` of its path.

## Certificates, feedback report and PDF documents (phase 1, Task-39)

### PDF documents
- Contract `Contracts\Documents\PdfRenderer::render(PdfDocument): string` (unchanged). `PdfDocument { template, data,
  watermark (TEST | VOID | null), locale (ar default) }`; templates are the enum `Enums\Documents\DocumentTemplate`
  (`payment_receipt`, `accredited_certificate`, `feedback_report`), each one a Blade view under
  `resources/views/documents/` (the ONLY Blade views of the project, operator decision Session 24). Nothing is served as
  HTML.
- `Services\Documents\DocumentHtmlComposer` renders the view with the document locale (`<html lang dir>`), the escaped
  data (`{{ }}` only, no raw output), the watermark and `DocumentAssets`: fonts, logo, level seals and the QR code
  (`bacon/bacon-qr-code` 3.1.1, SVG) are inlined as `data:` URIs read from `resources/documents/` (no SPA build at
  runtime). A `Content-Security-Policy` meta (`default-src 'none'; img-src data:; font-src data:`) blocks anything else.
- `Integrations\Documents\ChromiumPdfRenderer` (`chrome-php/chrome` 1.16.1): composes the HTML, takes the only render
  slot with `Cache::funnel('documents:pdf-render')->limit(1)` (web requests and Horizon jobs share it; waits up to 30 s,
  then 503 `document.renderer_busy` with `Retry-After: 30`), and prints through `Contracts\Documents\HtmlPdfPrinter`
  (`HeadlessChromePrinter`). The printer launches the binary with a private temporary profile (mode 700, removed in
  `finally`), the sandbox ON (never `--no-sandbox`), `--host-resolver-rules=MAP * ~NOTFOUND`,
  `--remote-allow-origins=http://127.0.0.1` (instead of `*`), then `Emulation.setScriptExecutionDisabled`,
  `Network.setBlockedURLs` (http, https, file, ftp, ws, wss), `setHtml`, waits for `document.fonts.ready` and sends
  `Page.printToPDF` over CDP with `printBackground`, `preferCSSPageSize`, `generateTaggedPDF` and
  `generateDocumentOutline`. The browser is always closed. Any engine failure is reported to GlitchTip and answers 503
  `document.render_failed`.
- Selection (`IntegrationServiceProvider`): `PDF_RENDERER=fake` in local / testing → `FakePdfRenderer`;
  `PDF_RENDERER=chromium` with an executable `PDF_CHROME_BINARY` → `ChromiumPdfRenderer`; otherwise
  `UnavailablePdfRenderer` (503 `document.renderer_unavailable`).
- Fonts (OFL, licences in `resources/documents/fonts/`): Alexandria 700 / 800 (Arabic headings, operator decision
  Session 24; the SPA's self-hosted Arabic WOFF2 files `alexandria-arabic-{700,800}.woff2` and `OFL-alexandria.txt`), IBM
  Plex Sans Arabic 400 / 600 / 700 (Arabic body, FULL TTF files) and the SPA's Montserrat Latin variable WOFF2 listed first
  in every stack for Latin letters and Western digits (so the Arabic-only Alexandria subset is enough for headings).
  Tajawal is no longer shipped. Changing the display font again: copy the OFL files into `resources/documents/fonts/`, add
  them to `DocumentAssets::FONTS` and change the two `Moashir Heading` rules of
  `resources/views/documents/partials/fonts.blade.php`.
- Identity: colours of the identity guide as CSS variables (`partials/tokens.blade.php`), the on-light logo and the
  star from Task-38, the four level seals from Task-40 with the "2013" outlines removed; the seal prints the ISSUE year
  (operator decision). Percentages inside Arabic sentences are `<bdi dir="ltr">87.50%</bdi>` (number then sign).
  Dates: Gregorian + Umm al-Qura with Western digits, Asia/Riyadh.
- `php artisan moashir:documents:render-samples --output=<dir>` prints fictional samples (gold certificate with a
  fictional entity logo, silver certificate without a logo, participation certificate, Arabic and English feedback
  reports, a TEST receipt) with the configured renderer; the deployment smoke check uses it.
- Entity logo (Task-47): when the certificate keeps a logo, the template prints it beside the entity name
  (`.certificate__entity-row`, at most 36 × 18 mm, `object-fit: contain`) as an inline `data:image/png|jpeg` URI; any
  other value is ignored. Without a logo the markup and layout are the same as before.

### Certificate data
- `certificates` (append-only, trigger + model guard; never deleted, also kept by the permanent entity deletion, P-02):
  `public_id`, `number` (`CRT-YYYY-######`, gap-free series `certificates.accredited` per Asia/Riyadh year; requests
  opened by a TEST payment get `TEST-CRT-YYYY-######` from their own series and a TEST watermark), `verification_code`
  (28 characters of the Crockford alphabet = 140 random bits, unique; shown in groups of four), `kind` (`accredited`),
  `level` (`bronze | silver | gold | diamond | null` = participation certificate), `status` (`issued | suspended |
  revoked | superseded`), `is_test`, entity, assessment, `result_snapshot_id`, `score_percent`, frozen `snapshot` (entity name,
  reference, request number, percent, level, the thresholds used, result version), `issued_at`, `issued_by_user_id`,
  `pdf_file_id` (stored PERMANENTLY, Q-80; set once), `entity_logo_file_id` (Task-47: the entity's logo AT ISSUE, read
  under a share lock on the entity row in the issuing transaction; immutable through the trigger, migration
  `2026_11_04_120200`; the PDF — rendered later by the job or on the first download — always uses this file, so an
  issued certificate never changes when the logo changes; the logo file is kept while a certificate references it),
  `replaces_certificate_id` + `reissue_reason`, `revoked_at` /
  `revoked_reason` / `revoked_by_user_id`, `superseded_at`, `suspended_at` / `suspended_reason` (the Super Admin's reopen
  reason, staff-internal, never returned by the API). Partial unique index: ONE `issued` certificate per assessment and
  kind. The trigger allows only `pdf_file_id` (once), the `issued → suspended | revoked | superseded` moves and the
  `suspended → revoked | superseded` moves; a suspension is never undone or rewritten (migration `2026_11_03_130200`).
- `certificate_verification_logs`: every public verification (`certificate_id`, `outcome`, `ip_hash` = HMAC-SHA256 of
  the address with the application key, never the address), pruned after 365 days by
  `moashir:certificates:prune-verification-logs` (daily 03:30).
- Settings (`settings` table, group `certificates`): `certificates.level_thresholds` (default bronze 60.00, silver
  70.00, gold 80.00, diamond 90.00; strictly ascending, 0.01..100, two decimals) and
  `certificates.accredited_issuance_enabled` (default true). The level is decided on the two-decimal percentage printed
  on the certificate (69.995 → 70.00 → silver); below bronze → participation certificate without a level.

### Issue, re-issue, revoke
- **On completion (Q-49, Q-181):** every completed accredited result gets a certificate while issuance is on;
  `issue_certificate` is still accepted from older clients and ignored (Task-79). `AssessmentCompleted` carries the completing assessor; the queued listener `IssueCertificateOnCompletion` (3 tries)
  runs `Actions\Certificates\IssueAccreditedCertificate`: issuance switched on, accredited request `completed` with a
  result, entity not frozen; idempotent (the issued certificate of the request is returned). It writes
  `certificate.issued`, sends ACC-09 (entity: link `/certificates`; System Administrators: `/admin/requests/{id}`) and
  queues `RenderCertificatePdfJob` (stored PDF; first download renders it if the job has not run).
- **Later by the completing assessor:** `POST /assessments/{assessment}/certificate { reason? }` (ability
  `certificates.accredited-certificate.issue` = `certificates.issue.accredited` + the assessor's own completed
  assignment; others 403 / 404) → 201 new or 200 existing; 422 `certificate.issuance_disabled`, 409
  `certificate.assessment_not_completed`, 409 `entity.deleted`. The Super Admin needs `reason` (422 `reason_required`,
  SEC-04).
- **Revoke (Q-68):** `POST /certificates/{id}/revoke { reason 10..1000 }` (`certificates.manage` within the assessment
  record scope = System Administrator; Super Admin with SEC-04) → `revoked`, audit `certificate.revoked` with the reason,
  CERT-01 to the entity (with the reason) and the System Administrators; 409 `certificate.not_issued`.
- **Re-issue (Q-49):** `POST /certificates/{id}/reissue { reason 10..1000 }` (same ability) → 201 a NEW certificate (new
  number and code, current result snapshot, current entity name, logo and thresholds, `replaces`), the source becomes
  `superseded` when it was issued or suspended (a revoked source stays revoked); 409 `certificate.superseded`,
  `certificate.replacement_exists`, `certificate.assessment_not_completed`. Audits `certificate.superseded` +
  `certificate.reissued`; ACC-09 again.
- **Suspension on reopen (operator decision Session 24):** when the Super Admin reopens a completed accredited request
  (`POST /assessments/{id}/reopen`) or forces it out of `completed` (`/transition/force`), the issued certificate becomes
  `suspended` in the same transaction (`Contracts\Assessments\AssessmentReopenStep`, bound to
  `Services\Certificates\CertificateSuspender`): audit `certificate.suspended`, CERT-02 to the entity (link
  `/certificates`, without the reason) and the System Administrators. While suspended: verification answers
  `under_review` with `level` and `percent` hidden, downloads answer 409 `certificate.suspended`, the System Administrator
  may still revoke it (CERT-01). When the request is completed again, the completion listener (or a later
  `POST /assessments/{id}/certificate` when the assessor opted out) issues a NEW certificate that `replaces` the suspended
  one (`reissue_reason` = the reopen reason, staff only) and the suspended one becomes `superseded`; ACC-09 is sent. With
  issuance switched off in the settings the certificate stays suspended until a System Administrator re-issues or
  revokes it. Super Admin score overrides without a reopen do not touch the certificate (Task-39 decision 5).

### Certificate endpoints and shapes
| Method | Path | Ability / limiter | Notes |
|---|---|---|---|
| GET | `/certificates` (`filter[status]` = comma list of statuses, `filter[level|entity_id|assessment_id]`, `search` = number, `page`, `per_page` ≤ 100) | `can:viewAny` (entity `certificates.own.view`, staff `certificates.manage`) | entity: own; staff: certificates of assessments in their record scope (System Administrator all accredited; the Award Secretary sees none in phase 1); `meta.status_counts { issued, suspended, revoked, superseded }` under the same scope and filters, ignoring `filter[status]` (Task-46) |
| GET | `/certificates/{uuid}` | `can:view` | owner or manager, 404 otherwise |
| POST | `/certificates/{uuid}/download` | `can:download`, `throttle:downloads` | `{ url, expires_at, file_name }` (signed 5 min, user-bound; `GET /files/{file}/download` audits `file.downloaded`); 409 `certificate.revoked` / `certificate.superseded` |
| POST | `/certificates/{uuid}/revoke`, `/reissue` | `can:revoke` / `can:reissue`, `throttle:sensitive` | see above |
| POST | `/assessments/{id}/certificate` | `can:certificates.accredited-certificate.issue,assessment`, `throttle:sensitive` | see above |
| GET | `/verify/{code}` | public, `throttle:certificate-verify` (30/min per IP) | QR target of the SPA page `/verify/{code}`; dashes, spaces and case are ignored |
| GET | `/content/certificate-levels` | public, `throttle:public` | `{ levels[{ level, label{ar,en}, min_percent }], participation_below }` from the current settings (home page section "excellence levels"; a level ends 0.01 below the next minimum) |
| GET / PUT | `/settings/certificates` | `can:settings.certificates-section.manage` (`settings.certificates.manage`), PUT `throttle:sensitive` | `{ accredited_issuance_enabled, level_thresholds{ bronze, silver, gold, diamond } }`; PUT partial with `reason` (Super Admin required, SEC-04); each changed key audited `settings.certificates_updated` |

`Certificate` = `{ id (uuid), number, kind, level, level_label{ar,en}|null, status, is_test, percent, request{ id, number,
path, path_label }, entity{ id, reference, name{ar,en} }, verification_code (grouped), verification_url, issued_at,
revoked_at, revoked_reason, superseded_at, suspended_at, replaces{ id, number }|null, has_pdf }`; staff readers also get `issued_by{ id,
name }`, `revoked_by{ id, name }`, `reissue_reason` (the assessor is never shown to the entity, Q-19). Verification =
`{ result: valid | under_review | revoked | superseded | not_found, certificate: { number, kind, level, level_label, percent,
entity_name{ar,en}, issued_at, is_test } | null }` — 200 also for unknown codes (no enumeration signal), never a
reason, never ids.

### Feedback report (accredited, §9.1, Q-80)
Generated ON DEMAND from the immutable result snapshot (scores) and the assessor's feedback items (strengths,
improvement points, notes per sub-criterion); nothing is stored as a file.
- `GET /assessments/{id}/feedback-report` (ability `reports.feedback-report.view` = `reports.feedback.view` + assessment
  record scope; accredited path only, other paths 404): before completion 200 `{ assessment_id, number, path,
  path_label, status, is_frozen, available: false }` (Q-94); then `{ …, available: true, source: assessor_assessment,
  result_version, entity{ id, reference, name{ar,en}, type, type_label{ar,en}, region{ar,en}, city{ar,en} },
  completed_at, completed_on, completed_on_hijri{ar,en}, total, max_total, percent, certificate{ id, number, level,
  level_label }|null, assessor_summary{ body, version }|null, criteria[{ criterion_id, code, name{ar,en}, weight, earned, percent, subs[{ sub_id, code,
  name{ar,en}, weight, earned, percent, strengths, improvement_points, notes }] }] }`. Readers: the entity (own), the
  assessor of the request, the System Administrator and the Requests Follower (their path scope); never an assessor
  identity.
- `POST /assessments/{id}/feedback-report/export { locale?: ar | en }` (same ability, `throttle:exports`; default
  language = request language) → `{ url, expires_at, file_name }`; 422 `report.not_available`. The URL is a temporary
  signed route (5 minutes, bound to the requester) to `GET /assessments/{id}/feedback-report/pdf?locale=&user=`
  (`throttle:downloads`), which renders the PDF (A4 portrait, page X of Y, TEST watermark for a request opened by a test
  payment), streams it (`no-store`) and audits `feedback_report.exported` (class C download, Q-87). 403
  `signature.invalid` for another user, 410 `signature.expired`, 503 while no renderer is available.
- ACC-10 keeps its link `/assessment/accredited/result/{id}/feedback`.
- The PDF prints the «ملخص المقيّم» block after the totals and before the per-criterion section when
  `assessor_summary` is filled (Task-64, Q-154); it is hidden otherwise.

### Assessor summary «ملخص المقيّم» (Task-64, Q-154)
- Table `assessment_summaries` (append-only, versioned like `assessment_feedback_items`): `assessment_id`, `body`
  (CHECK not blank), `author_user_id`, `version` (≥ 1, unique per assessment), `change_reason`, `superseded_at`,
  `created_at`; partial unique index = one current row per assessment; trigger `moashir_guard_supersede_once()` (update
  only to set `superseded_at` once; delete only during entity erasure) and the truncate guard.
- `PUT /assessments/{id}/review/summary { summary: string|null (required key, ≤ moashir.assessment.summary_max_chars =
  2000 characters, trimmed), reason? }` — `can:score` (active assignee) + `throttle:assessment-write`. Same text = no-op;
  changed text = supersede + `version + 1`; empty / `null` = supersede and clear. Answers `{ summary: { body, version,
  updated_at }|null }`. Errors: 409 `assessment.summary_not_applicable` (advanced path), 409 `state.transition_illegal`
  (not `under_review`, e.g. Super Admin on a completed request), 403 when the caller is not the active assignee (the
  assignment ends at completion, so a completed request answers 403 to its former assessor), 404 for an unrelated
  assessor, 422 (too long, Super Admin without `reason` → `reason_required`), 409 `entity.deleted` (frozen entity).
- Audit `assessment.summary_saved` `{ version, cleared }` (never the text). No notification.
- `GET /assessments/{id}/review` adds `summary` (assignee and read-only review). Completion never requires it.

## Excellence level on staff lists, dashboards and the entity home (Task-67)

**Level rule (one place).** `Entity::levelCertificate()` is a has-one-of-many relation: the entity's ACCREDITED
certificate with status `issued`, latest by `issued_at`, then `id`. Revoked, suspended and superseded certificates never
count (a revoked newest certificate falls back to the previous issued one). `level` = the certificate's metal level
(`bronze | silver | gold | diamond`), `participation` when the certificate has no metal level, `null` without an issued
certificate. Test certificates (`TEST-CRT-…`, demo data) count like any issued certificate. Read-side helpers:
`App\Support\Certificates\EntityLevelQuery` (eager-load constant, filter, sort, dashboard key, open-request flag) and
`EntityLevelPresenter`; enums `EntityLevel`, `EntityLevelFilter`, `EntityLevelBucket`. Levels are always set-based: one
eager-load query per page (or a correlated sub-select for sort / dashboard), never one query per row — the list and the
queue have query-count tests.

**"Open accredited request"** = an accredited request in `draft | submitted | assigned | under_review | needs_completion`
(paid and not finished; `awaiting_payment`, `completed`, `refunded`, `declined_incomplete` are not open).

- `GET /entities` rows (all three variants: full staff, name and contact, summary) and `GET /entities/{id}` add
  `level: "bronze"|"silver"|"gold"|"diamond"|"participation"|null`, `level_certificate_number: string|null`
  (e.g. `"CRT-2026-000014"`) and `level_valid_until: "YYYY-MM-DD"|null`, taken from the latest VALID certificate (issued, not
  past `valid_until` — Task-79, Q-160). The full staff view (`StaffEntityResource`, list and record) also adds
  `has_open_accredited_request: bool`; the other variants do not carry this key.
- `filter[level]` ∈ `none | participation | bronze | silver | gold | diamond` (`none` = no valid certificate); anything
  else → 422 `validation_failed` (`errors["filter.level"]`). The filter also narrows `meta.stats` and the CSV export, and
  the export audit records `filters.level`.
- `sort=level` orders none < participation < bronze < silver < gold < diamond; `sort=-level` the reverse; ties by `id`.
- `GET /requests` rows and `GET /requests/{id}` (and the assignment / advanced action answers built on it): `entity.level`
  (same values; no certificate number in the queue).
- Dashboard widget `entities_by_level` (System Administrator and Executive boards, `bars`): non-erased, non-deleted
  entities, fixed order and keys, zero counts kept:

```
{ "key": "entities_by_level", "data": [
  { "key": "none", "count": 4 }, { "key": "in_progress", "count": 1 }, { "key": "participation", "count": 2 },
  { "key": "bronze", "count": 3 }, { "key": "silver", "count": 1 }, { "key": "gold", "count": 2 }, { "key": "diamond", "count": 0 } ] }
```

  `in_progress` = no level AND an open accredited request; an entity with a level and an open request stays in its level.
  Labels come from the SPA i18n (level names already exist; widget title is an SPA key).
- Entity board card `{ "key": "accepted_evidence_count", "value": 7, "format": "count", "link": null }`: the entity's
  evidence files that are present (not removed) and passed the malware scan (`clean`), across all its requests. The
  domain has no per-file acceptance by the assessor; "accepted" = accepted by the platform.
- Entity board widget `criteria_progress` (main criteria, used by the home strengths / improvements block) now reads the
  latest ELIGIBLE accredited result with the insights rules (`AccreditedResultsReader` + `EligibleResultSelector`:
  completed accredited, newest first, revoked / suspended excluded); never a self result. `[]` when there is none. The
  `latest_result` widget and the `latest_result_percent` card keep the latest self OR accredited result as before.
- `GET /dashboard/action-center` → `meta: { total, generated_at: "2026-11-05T08:00:00+00:00" }`.

No migration, permission, package or environment variable.

## Excellence tools — wave 1 (Task-64, analysis-excellence-tools ET-01 … ET-30)

Computed ON READ from the frozen result snapshot, the current feedback items, the current assessor summary and the
current rule versions; nothing derived is stored.

### Eligibility and selection
- Eligible result = an ACCREDITED assessment with status `completed`, a current result snapshot and a latest
  certificate (by id) that is neither `revoked` nor `suspended`. Self is never eligible (404 on tool routes); advanced is
  never eligible (409 `not_accredited`). Verified date = `completed_at`; default = the latest (tie → higher id);
  previous = the eligible result just before the one shown (`Support\Insights\EligibleResultSelector`,
  `ResultEligibility`).
- Exclusion notices: a revoked certificate (`certificate_revoked`) or a reopened / suspended result (`under_review`)
  newer than the selected result.
- Classification compares the displayed percent (2 decimals, half-up) with the CURRENT rules
  (`Support\ValueObjects\GapThresholds`, `MaturityBounds`); the previous result of a delta uses the same rules.

### Settings (`/api/v1/settings/insights`, `settings.assessment.manage`)
- `GET` → `{ gap_thresholds{ critical_below, strength_from, version }, maturity_bands{ developing_from, mature_from,
  leading_from, version }, aligned (bands 50/80 = gaps), effective_since|null }`. Defaults 50.00 / 80.00 and
  50.00 / 70.00 / 80.00, version 1.
- `PUT { gap_thresholds?: { critical_below, strength_from }, maturity_bands?: { developing_from, mature_from,
  leading_from }, reason? }`: strings with at most 2 decimals, `0 < … ≤ 100`, strictly ascending (422 on the group
  key); the server raises `version` by one on a real change only; audit `settings.insights_updated` (before / after);
  Super Admin needs `reason` (422 `reason_required`) and raises SEC-04. Advisory lock `settings:insights`.

### Interpretation texts (`/api/v1/content/maturity-texts`, `content.insights.manage`)
- Table `maturity_interpretations` (`band` ∈ 4 bands, `main_criterion_code` nullable, `text` jsonb `{ar,en}` nullable for
  the four seeded generic rows, `updated_by_user_id`); unique generic row per band and unique (band, code) override.
- `GET ?page=&per_page=` (paginated, generic rows first) → `[{ id, band, main_criterion_code, is_generic,
  in_published_version|null, text{ar,en}|null, updated_at }]`.
- `PUT /{band} { main_criterion_code?, text{ ar, en } }` upserts (plain text, ≤ 600 characters each, no markup; code
  must exist in `main_criteria`); unknown band 404. `DELETE /{id}` removes an override (204); a generic row answers 422
  `insights.generic_text_not_deletable`. Every change audits `content.maturity_texts_updated` (band, code, before / after).
- Resolution: override (band, main code) > generic text of the band > none.

### Endpoints (all `/api/v1`, signed-in stack)
| Method | Path | Ability |
|---|---|---|
| GET | `/entities/me/insights` | `reports.insights.view-own` (entity + `reports.insights.own.view`) |
| GET | `/entities/me/insights/results?page=` | same |
| GET | `/entities/me/report-library?page=` | same |
| GET | `/entities/{entity}/insights`, `/insights/results`, `/report-library` | `reports.insights.view-entity` (`reports.insights.view` + global reader; deleted entity needs `entities.delete`) |
| GET | `/assessments/{id}/insights/strengths-gaps`, `/maturity`, `/executive-summary` | `reports.insights.view-assessment` |
| POST | `/assessments/{id}/board-report/export { locale? }` | same + `throttle:exports` |
| GET | `/assessments/{id}/board-report/pdf?locale=&user=&signature=` | signed (5 min, user-bound) + same + `throttle:downloads` |
| GET / PUT | `/settings/insights` | `settings.assessment.manage` (`throttle:sensitive` on PUT) |
| GET / PUT / DELETE | `/content/maturity-texts` … | `content.insights.manage` (`throttle:content-write` on writes) |
| PUT | `/assessments/{id}/review/summary` | `can:score` (see "Assessor summary") |

`reports.insights.view-assessment`: self path 404; no insights permission 403 (assessors, Content / Finance Officer …);
owner with `reports.insights.own.view` or global reader covering the accredited path with `reports.insights.view`
allowed; anything else 404 (another entity, follower without the accredited path). Super Admin by `Gate::before`.

Shapes:
- `ResultRef` = `{ assessment_id, number, percent, snapshot_version, verified_at, criteria_version{ id,
  version_number, label{ar,en}|null }|null, certificate{ id, number, status, level|null }|null }`. `certificate` is
  `null` when the result has no certificate; `number` is the certificate number (`CRT-2026-000003`, Task-69 G-2),
  also for a revoked or suspended certificate (read it with `status`).
- Overview (200 always): `{ selected: ResultRef|null, exclusions[{ assessment_id, number, reason }], empty_state{ reason:
  no_verified_result|only_self|in_progress|under_review, cta{ kind: start_accredited|view_request, assessment_id|null
  }|null }|null, in_progress_request{ assessment_id }|null, rules{ gap_thresholds_version, maturity_bands_version,
  effective_since } }`. `cta` is `null` for staff. A follower without the accredited path sees no result.
- Results: `ResultRef[]` newest first, 15 per page, `meta.pagination`.
- Strengths and gaps: `{ result, rules{ critical_below, strength_from, version, effective_since }, counts{ strength,
  opportunity, critical_gap }, mains[{ code, name, percent, class }], items{ strengths[], opportunities[],
  critical_gaps[] } }`; item = `{ main_code, main_name, sub_code, sub_name, weight, earned, percent, lost_points, class,
  assessor{ strengths, improvement_points } }` (notes are never shown). Order: gaps and opportunities by lost points
  desc, percent asc, criteria order; strengths by percent desc, weight desc, criteria order.
- Maturity: `{ result, previous: ResultRef|null, versions_differ, rules{ developing_from, mature_from, leading_from,
  version, effective_since }, overall{ percent, band, delta_pp|null, previous_band|null }, mains[{ code, name,
  description, weight, earned, percent, band, interpretation{ar,en}|null, comparison: matched|new_in_version|no_previous,
  delta_pp|null, previous_band|null, subs[{ code, name, percent, band, delta_pp|null }] }], removed_since_previous[{ code,
  name, previous_percent }] }`. `delta_pp` is a signed string with 2 decimals (`"-2.50"`).
- Executive summary: `{ result, entity{ name, has_logo, logo_url|null }, score{ percent, earned, total_weight }, certificate{ standing:
  level|participation|not_issued, level|null }, change{ delta_pp|null, previous|null, versions_differ },
  assessor_summary{ body, version }|null, profile{ classes{…}, bands{…} }, strong_areas[item + quote],
  priority_gaps[item + quote], rules{ …versions, gap_thresholds, maturity_bands } }` (top 3 each; priority gaps =
  critical gaps then opportunities). `entity.logo_url` (Task-69 G-1) is a short-lived signed link to
  `GET /api/v1/files/{public_id}/download?user=…&expires=…&signature=…` (the existing file-download route, bound to the
  viewer, TTL `moashir.finance.download_ttl_minutes`), never a storage path; `null` when the entity has no logo, the
  logo is not scanned clean, or the viewer may not view the entity logo (`EntityPolicy::viewLogo`).
- Report library: rows `{ result, eligible, exclusion_reason|null, items[{ kind: feedback_report|board_report|certificate,
  available, status|null, certificate_id|null }] }` per accredited result with a snapshot (completed, revoked or
  reopened), newest first, 15 per page; `meta.coming_soon[{ kind: periodic|annual|benchmark }]`. The
  `feedback_report` item also has `pdf_available` (bool) and `pdf_url` (string|null, Task-69 G-4): a signed link to
  `GET /api/v1/assessments/{id}/feedback-report/pdf?locale=…&user=…&expires=…&signature=…` bound to the viewer
  (another user 403 `signature.invalid`, expired 410), in the request language, TTL
  `moashir.documents.download_ttl_minutes`; every download audits `feedback_report.exported`. `pdf_available` is `false`
  and `pdf_url` `null` when the request is not completed or the viewer lacks the feedback-report ability
  (`reports.feedback.view` + record scope). The `board_report` and `certificate` items have no PDF keys (board report:
  `POST …/board-report/export`; certificate: `POST /certificates/{id}/download`).
- Errors: 409 `insights.result_not_eligible` with `meta.reason` = `not_completed | not_accredited | certificate_revoked |
  under_review` (tools, export, PDF), 404 hides other entities, 403 missing permission, 422 `page` / `locale`.

### Board report PDF
Template `documents.board_report` (A4 portrait, ar RTL / en LTR, embedded fonts, entity logo when uploaded, no QR,
footer «وثيقة داخلية للجهة — سرية», rules and snapshot versions). One page up to 12 main criteria (checked with the
rendered samples `moashir:documents:render-samples`, ar and en, longest summary and quotes); the assessor summary is
cut at 600 characters on a word boundary with the pointer to the feedback report, quotes at 200
(`moashir.insights.*`). Not stored; every download audits `board_report.exported` (`assessment_id`, `snapshot_version`,
`locale`, both rule versions, tag `download`). File name `board-report-{request_number}-{locale}.pdf`.

### Permissions
`reports.insights.own.view` (entity, not confidential), `reports.insights.view` (System Administrator, Requests
Follower; confidential), `content.insights.manage` (Content Officer, System Administrator). Assessors and the other
roles have no access. Apply with `php artisan moashir:permissions:sync`.

## Contact messages (phase 1, Task-42)

Operator decision (Session 24): a REAL contact form on the public site and a back-office inbox with replies. Guest senders
are answered by e-mail; signed-in senders by e-mail AND in the portal, and their messages appear in "My messages".

### Data
- `contact_messages`: `public_id` (uuid, used in every URL), `reference` (`CNT-YYYY-######`, gap-free series
  `contact.messages` per Asia/Riyadh year in `yearly_number_sequences`), `status` (`new | in_progress | replied | closed`),
  `enquiry_type` (`general | self_assessment | accredited_assessment | payments | technical_support | other |
  privacy_request`; `privacy_request` = «طلب يتعلق بالبيانات الشخصية» / "Personal data request", Task-49),
  `sender_name`, `sender_email` (lower case), `sender_mobile` (`+9665XXXXXXXX`, ENCRYPTED at rest, Q-75),
  `organization_name`, `locale` (`ar | en`, the request language), `user_id` / `entity_id` (linked sender), assignee,
  `privacy_terms_version_id` + `privacy_consent_at` (consent record), `last_activity_at`, `replied_at`, `closed_at` /
  `closed_by_user_id`. CHECK constraints on every enum, the reference, `closed_at` and the link.
- `contact_message_entries`: the thread, `kind` (`message` = from the sender, `reply` = staff answer e-mailed to the sender,
  `note` = internal, never shown to the sender), `body` (1..5000), author; immutable (trigger + model guard against
  UPDATE), deleted only by the retention purge and the erasure tools.

### Rules
- **Linking (PM security decision):** a message is linked to a user (and the user's entity) ONLY when it is sent from a
  valid signed-in session (`Services\Contact\SignedInSender`: web session, not idle / absolute expired, MFA satisfied).
  The name, e-mail and organisation are then taken from the account (entity name in the request language), never from the
  form. A guest message whose e-mail matches an account is NOT linked; it stays a guest message answered by e-mail only.
- **Spam protection (no third-party CAPTCHA):** honeypot field `website` (must be empty; a filled one answers the normal 201
  with a decoy reference and stores and sends nothing, logged as `contact.submission_dropped` without data); limiter
  `contact-messages` = 5 per 10 minutes and 20 per day per IP, 3 per hour per sender e-mail (the account e-mail when signed
  in), values in `config/moashir.php` (`moashir.contact.*`). The acknowledgement never echoes the sender's text or name
  (the form cannot be used to relay content to third-party mailboxes).
- **Back office:** permission `contact.messages.manage` (GRANTABLE, module `content`, confidential; seeded to the System
  Administrator and the Content Officer; granted to any other role through the permissions matrix; applied by
  `moashir:permissions:sync`). Every holder sees every message (one shared inbox). Assignment accepts only active staff
  holding the permission (`GET /admin/contact-messages/assignees`). The Super Admin must give a `reason` (10..1000) for
  every inbox action and raises SEC-04 (`StaffActionReason`).
- **States:** `new → in_progress` (assigned), `→ replied` (staff reply), `→ in_progress` (sender follow-up on a replied
  thread; a follow-up on a `new` thread keeps `new`), any open state `→ closed` (reason optional), `closed → in_progress`
  (reopen). Replies, assignments and sender follow-ups on a closed thread answer 409 `contact.message_closed`; an illegal
  close / reopen answers 409 `state.transition_illegal`. Internal notes are allowed in every state and change nothing.
- **Audit** (`contact_message.received | assigned | note_added | replied | follow_up_received | closed | reopened`): reference,
  status, enquiry type, entry id, assignee id and `is_linked` only — never a message body, name, e-mail or mobile number.

### Contact endpoints
| Method | Path | Ability / limiter | Body / answer |
|---|---|---|---|
| POST | `/contact-messages` | public (session used when present), `throttle:contact-messages` | `{ name, email, mobile?, organization?, enquiry_type, message (10..5000), privacy_consent: true, website: "" }` (name / email required for guests, ignored when signed in; mobile `05XXXXXXXX` or `+9665XXXXXXXX`) → 201 `{ reference, created_at }` (+ `id` only when the message was linked to the signed-in sender, Task-46); 422 field errors; 429 `rate_limited` |
| GET | `/me/contact-messages` (`filter[status]` = comma list of statuses, `page`, `per_page` ≤ 100) | signed in (`can:viewOwn`) | paginated `MyContactMessage` (own linked messages only), `meta.status_counts { new, in_progress, replied, closed }` (own messages, ignoring the filter) and `meta.unread_replies` |
| PATCH | `/me/contact-messages/{id}/seen` | signed in; another sender's message answers 404 for everyone, the Super Admin included | → `MyContactMessage` + `entries`; sets `sender_seen_at` (idempotent, not audited) |
| GET | `/me/contact-messages/{id}` | signed in; another sender's message answers 404 for everyone, the Super Admin included | `MyContactMessage` + `entries` |
| POST | `/me/contact-messages/{id}/replies` | signed in, `throttle:contact-follow-ups` (10 per 10 minutes per user) | `{ body (2..5000) }` → 201 `MyContactMessage`; 409 `contact.message_closed` |
| GET | `/admin/contact-messages` (`filter[status|enquiry_type|from|to|linked|assignee]`, `search`, `page`, `per_page` ≤ 100) | `contact.messages.manage` | paginated `ContactMessage`, `meta.stats { new, in_progress, replied, closed }`; `from` / `to` are `Y-m-d` Riyadh days, `linked` `true | false`, `assignee` `me | none | <user id>`, `search` (≥ 2 characters, Arabic-normalised) on reference, name, e-mail and organisation |
| GET | `/admin/contact-messages/assignees` | `contact.messages.manage` | `[{ id, name }]` |
| GET | `/admin/contact-messages/{id}` | `contact.messages.manage` | `ContactMessage` + `entries` (notes included) |
| POST | `/admin/contact-messages/{id}/assign` | `contact.messages.manage`, `throttle:sensitive` | `{ assignee_id: int | null, reason? }` → `ContactMessage`; 422 `contact.assignee_not_eligible` |
| POST | `/admin/contact-messages/{id}/notes`, `/replies` | `contact.messages.manage`, `throttle:content-write` | `{ body (2..5000), reason? }` → 201 `ContactMessage` |
| POST | `/admin/contact-messages/{id}/close`, `/reopen` | `contact.messages.manage`, `throttle:sensitive` | `{ reason? }` → `ContactMessage` |
| POST | `/privacy/contact-messages/export` | `privacy.requests.manage`, `throttle:exports` | `{ email, reason (10..1000) }` → `{ generated_at, email, messages[] }` |
| POST | `/privacy/contact-messages/erase` | `privacy.requests.manage`, `throttle:exports` | `{ email, reason }` → `{ erased_messages, erased_entries }` |
| GET / PUT | `/settings/contact-messages` | `settings.general.manage` (System Administrator), PUT `throttle:sensitive` | `{ retention_months, retention_limits{ min: 1, max: 120 } }`; PUT `{ retention_months, reason? }` (Super Admin: reason + SEC-04); audit `settings.contact_updated` |

`ContactMessage` (staff) = `{ id, reference, status, enquiry_type, sender{ name, email, mobile, organization, locale },
is_linked, linked_user{ id, name }|null, entity{ id, reference, name{ar,en}, status }|null, assignee{ id, name }|null,
entry_count (lists), created_at, last_activity_at, replied_at, closed_at, closed_by{ id, name }|null, entries[{ id, kind,
body, author{ kind: sender|staff, id|null, name }, created_at }] (detail) }`.
`MyContactMessage` (sender) = `{ id, reference, status, enquiry_type, can_reply, created_at, last_activity_at, replied_at,
closed_at, has_unread_reply, preview, last_entry_author: sender|team, entries[{ id, kind: message|reply, body, author:
sender|team, created_at }] (detail) }` — never a note, never a staff identity. `has_unread_reply` = a team reply after the
sender last opened the thread (`sender_seen_at`, set by the seen endpoint and by a sender follow-up); `preview` = the first
160 characters of the latest sender-visible entry, whitespace collapsed (one sub-select, Task-46). Enquiry-type labels: `lang/*/contact.php` (e-mails) and the SPA i18n by value.

### Notifications
| Code | When | To | Channels | Mandatory |
|---|---|---|---|---|
| CNT-01 | message received | holders of `contact.messages.manage` (audience `contact_handler`), never the sender | I + E | no |
| CNT-02 | staff reply | the sender: linked → e-mail (with the reply text) + in-app (link `/messages/{id}`, no reply text); guest → e-mail only | I + E | yes |
| CNT-03 | acknowledgement | the sender (account or address), reference + enquiry type only; linked senders get the `/messages/{id}` button | E | yes |
| CNT-04 | sender follow-up | the assignee, else every handler | I + E | no |
| CNT-05 | assigned to you | the assignee (not when assigning oneself) | I + E | no |

Staff links: `/admin/contact-messages/{id}`. Guest e-mails use the language of the request (`Accept-Language`); linked
senders receive them in their profile language. Action Center: `answer_contact_messages` (count of `new` messages,
warning, link `/admin/contact-messages?status=new`) for the holders of the permission.

### Retention and data-subject requests (PDPL)
- Closed messages are kept `contact.retention_months` months after closing (default **24**, 1..120, setting above); open
  messages are kept until closed. `moashir:contact-messages:purge-closed` (scheduled daily at 03:45, without overlapping)
  deletes the expired threads and writes a `housekeeping_runs` row (`task = contact_message_purge`, `counts {
  purged_messages, purged_entries, retention_months }`) and, when something was purged, the audit `contact_messages.purged`
  with the counts only.
- Linked messages are part of the entity personal-data package (`POST /entities/{id}/export`, section `contact_messages`,
  sender-visible entries only) and are erased by the permanent entity deletion (`Services\Contact\ContactMessageEraser`,
  tagged erasure step).
- Guest messages (and any message of an address): `POST /privacy/contact-messages/export` / `erase` by e-mail (holders of
  `privacy.requests.manage`, reason required). The erasure writes a `housekeeping_runs` row (`contact_message_erasure`) and
  the audit `privacy.contact_messages_erased` with counts and the masked address; the export writes
  `privacy.contact_messages_exported` (masked address, message count; the audit subject is the exporting staff account).

## Entity portal contract (Task-46)
Additive fields, filters and counts for the approved entity portal (`agents/analysis/analysis-entity-portal-contract.md`
B-01 … B-07). No field was removed or renamed; no new permission key, package or environment variable.

- **Comma-list status filters:** `GET /me/contact-messages`, `GET /payments/mine` and `GET /certificates` accept
  `filter[status]=a,b` through the shared rule `Rules\EnumValueList` (one value still works; an unknown value, an empty item
  or a trailing comma answers 422 `validation_failed` with `errors["filter.status"]`). Their `meta.status_counts` come from
  `Support\StatusCounts` (one `GROUP BY status` query, every status present with 0 when empty) over the same scope and the
  other filters, never the status filter. The back-office inbox `meta.stats` uses the same helper.
- **Accredited progress (B-01):** `Services\Assessments\SubmissionProgressReader` reads the bound sub-criteria, the CV
  entries and the evidence once (`SubmissionCoverageReader`, shared with `SubmissionGapsReader`, so the progress and the
  submit check cannot disagree). A sub-criterion without CV and without required slots is complete.
- **Profile completion (B-04, Q-141):** `Support\ValueObjects\ProfileCompletion` holds the rule: required items
  `basic_data`, `account_owner`, `governance_grade`, `employees_count`, `annual_beneficiaries_count` and, since Task-47,
  `license_number`, `founded_on`; optional `phone`, `website_url`, `logo`; `percent` = floor(complete required / 7 × 100).
  `Entity::hasCompleteProfile()` (and `profile_completed`, `profile_completed_at`, the `complete_profile` item) is
  `percent = 100`.
- **Payment request link (B-05):** `request { id, number, path, path_label } | null` on `GET /payments/mine` rows,
  `GET /payments/{uuid}`, `POST /payments` (always `null`, a new order opens no request) and `POST /payments/{uuid}/verify`;
  read in one `whereIn` lookup through the contract `Contracts\Finance\OpenedRequestLookup` (finance never reads the
  assessments models directly).
- **Assessments tabs (B-07):** `filter[is_open]` uses the `openForViewer` scope (masked advanced outcomes stay open for the
  viewers they are masked from until the announcement), `filter[has_result]` the `exposingResult` scope (same rule as
  `Assessment::exposesResult()`).
- Deployment: migration `2026_11_04_100000_add_sender_seen_at_to_contact_messages_table` (`contact_messages.sender_seen_at`
  `timestamptz null`, reversible).

## Quality gates

```
composer lint            # Pint --test
composer analyse         # Larastan, level max
composer refactor:check  # Rector --dry-run
composer test            # Pest: Arch, Unit, Feature (PostgreSQL database moashir_testing)
composer test:parallel   # the same suite in parallel processes (moashir_testing_test_<token> per process)
composer security        # composer audit
composer quality         # all of the above, in order (sequential and parallel test runs)
```

`composer.json` sets `config.process-timeout` to 1200 seconds: the sequential suite takes several minutes and Composer's
default of 300 seconds stopped `composer quality` before it finished. CI uses the same value.

Parallel runs and PostgreSQL locks: every test process starts with `migrate:fresh`, whose `db:wipe` drops all tables in
ONE `DROP TABLE ... CASCADE` statement. On the current schema that single transaction holds about 2,000 entries in
PostgreSQL's shared lock table (tables, indexes, sequences, TOAST relations, constraints, row types). The table holds
`max_locks_per_transaction × (max_connections + background workers)` entries, about 7,800 with the defaults 64 / 100, so
four processes wiping at the same moment exhausted it (`SQLSTATE[53200] out of shared memory`). `Tests\TestCase`
therefore drops the tables one statement at a time (`Tests\Support\TableByTableSchemaWipe`, at most about 370 locks,
released after each statement) before `migrate:fresh`, which then finds nothing to drop. A test transaction holds
68 locks on average and at most about 570 (award flows), so eight processes stay well below the limit. CI also runs
PostgreSQL with `max_locks_per_transaction = 256` for headroom as the schema grows.

Tests use PostgreSQL (`moashir_testing`), the `array` cache/session drivers, the `sync` queue and fakes for SMS,
notifications, the payment gateway, the malware scanner (`FILES_SCANNER=fake`) and the PDF renderer (`PDF_RENDERER=fake`).
`Tests\TestCase` fakes the `local` disk for every test, so generated and uploaded files never reach `storage/app`.
The real renderer has one opt-in test (`tests/Feature/Documents/ChromiumRenderingTest.php`), skipped unless
`PDF_TEST_CHROME_BINARY` points to a `chrome-headless-shell` binary: `PDF_TEST_CHROME_BINARY=<path> composer test`.
`phpunit.xml` sets `memory_limit` to 2G: the architecture tests parse every file of `app/`, and a sequential run keeps a
little memory per test (about 0.03 MB for a plain request, up to about 0.7 MB for the long assessment and award flows), so
the whole suite (2,642 tests after the contact-messages step) needs more than the former 1G limit; finding what each test retains is a follow-up. See "Test isolation" below for how the suite is kept away from the development database.
`Tests\TestCase` calls `Http::preventStrayRequests()`, so any outbound HTTP call fails the test; the breached-password
tests also fake the HTTP client to throw and assert that nothing was sent. No test reaches an external service.

## Test isolation and destructive database commands

The development database is `moashir`; the test database is `moashir_testing`. They are kept apart in four layers:

1. **`.env.testing`** (committed, names and non-secret local defaults only). Laravel loads it instead of `.env` for
   Pest/PHPUnit and for any `php artisan … --env=testing`. It sets `APP_ENV=testing` and `DB_DATABASE=moashir_testing`,
   leaves `APP_KEY`, `DB_USERNAME` and `DB_PASSWORD` empty and uses the `array`/`sync` drivers. An empty
   `DB_USERNAME` makes PostgreSQL use the operating-system user (local fallback); otherwise export `DB_USERNAME` /
   `DB_PASSWORD` in the shell — real environment variables win over the file. Sail passes `DB_HOST=pgsql`, the port
   and the credentials to the container (`compose.yaml`) and deliberately not `DB_DATABASE`.
2. **`phpunit.xml`** pins `APP_ENV`, `DB_CONNECTION`, `DB_DATABASE` and `DB_URL` as both `<server>` and forced `<env>`
   values, so a variable exported in the shell cannot point the suite elsewhere. `tests/bootstrap.php` generates a
   random `APP_KEY` for each run (no key is stored in the repository) unless one is already exported.
3. **`Tests\TestCase::beforeRefreshingDatabase()`** asks the guard below before `RefreshDatabase` runs `migrate:fresh`;
   if the default connection is not a test database (for example because of a cached configuration), every test
   fails with the reason and nothing is dropped.
4. **The destructive-command guard** (`DatabaseSafetyServiceProvider`, `GuardDestructiveDatabaseCommand`,
   `DestructiveCommandGuard`), built on the framework's own `Prohibitable` switches
   (`DB::prohibitDestructiveCommands()` and `SeedCommand::prohibit()`).

Guarded commands: `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe` and forced
seeding (`db:seed --force`). A *test database* is one whose name ends with `_testing` (Laravel's parallel-testing
copies `…_testing_test_N` included).

Parallel runs (`composer test:parallel`, `pest --parallel`): Laravel gives every process its own copy named
`<database>_test_<token>` (`moashir_testing_test_1`, `moashir_testing_test_2`, …), creates it on first use and keeps it
for the next run, so the database user needs the `CREATEDB` privilege (true for the Sail superuser and for the local OS
user). The guard accepts exactly these copies of a `_testing` database and nothing looser: `moashir`, its parallel copy
`moashir_test_N`, `moashir_testing_N` (no `_test_` infix) and look-alikes such as `moashir_testing_backup` are refused.
Tests that check the active database name use `suiteTestDatabase()` (`tests/Pest.php`), which returns the name of the
current process (`moashir_testing` in a sequential run).

| Environment | Test database | Any other database |
|---|---|---|
| `testing` | allowed | refused |
| `local` | allowed | refused, unless the command is given `--confirm-database=<exact database name>` |
| `staging`, `production` and any other value | refused | refused (the confirmation is ignored) |

Seeding: plain `db:seed` and `--seed` stay available in `local` and `testing` (seeders only add idempotent, fictional
data); `db:seed --force` follows the table above; in `staging`, `production` and any other environment every
seeding run is refused, forced or not. Reference data is installed there with dedicated, idempotent commands:
`php artisan moashir:permissions:sync` and the breached-password import command.

Resetting the local development database on purpose (the only way, and only in `local`):

```
php artisan migrate:fresh --seed --confirm-database=moashir
```

A refused run prints the reason, then the framework's "This command is prohibited from running in this environment."
and exits with code 1. How it decides: at boot every console process locks the guarded commands unless *every*
configured connection points at a test database (seeding is locked in protected environments). For command-line runs
outside the `testing` environment, a `CommandStarting` listener re-decides with the real target (`--database`) and the
confirmation, and nested calls (for example `migrate:fresh` calling `db:wipe` and `db:seed`) follow that decision.
Laravel emits no console events while `APP_ENV=testing`, so there the boot-time decision alone applies.

## Demo data (review and demo environments)

`php artisan moashir:demo:install --accounts-file=<absolute path>` loads fictional demo data: one staff account per staff
role (MFA required), three entities with their owner accounts (registered and verified through the normal services),
the legal drafts of Task-49 published as the current terms and privacy versions (label `2026-11-draft`) and the published
accessibility page, the accredited price when none is set, one draft award cycle
(created by the demo Award Secretary) and two TEST-mode payments (one paid with a TEST receipt, which opens the
accredited draft through the normal listener, and one open order). It never creates the Super Admin.
For the excellence tools (Task-64) it also adds four completed ACCREDITED results opened by TEST payments (Al-Amal: a
silver then a gold result with an assessor summary, for the delta; Al-Khair: a participation result and a newer result
whose certificate is revoked), one self result for Al-Namaa (only self results) and fictional generic maturity texts.

Guards (`Services\Demo\DemoInstallGuard`): refused unless `APP_DEMO_DATA=true`; outside `local` / `testing` also
refused unless the environment opted in to the demo posture with `MOASHIR_DEMO=true` (Task-51 / Task-52, reason
`posture_not_demo`); refused when an entity exists that the
command did not create (the command records its entities and users in `demo_records`); refused when the demo is already
installed. Every refusal is logged as critical (`platform.demo_data_refused` with the reason only). Passwords are random
(24 characters) and are written only to the accounts file: a new file outside the application directory, created
exclusively with mode 600 before the database work and removed again if the installation fails. The console output
never contains a password. `php artisan moashir:demo:rotate-passwords --accounts-file=<absolute path>` issues new
passwords for every demo account, ends their sessions and writes a new file. The deployment kit wraps both commands
and moves the file to `/root/moashir-<env>-demo-accounts.txt` (see `deploy/RUNBOOK.md`).

### Excellence-tools cases on an installed demo (`--insights-only`, Task-71)
A demo installed before Task-64 has no completed accredited result, so the excellence tools show only their empty
states. `php artisan moashir:demo:install --insights-only` adds ONLY the Task-64 cases above to an installed demo, in one
transaction:
- the four accredited results with their own TEST payments and TEST receipts (number empty), feedback, the assessor
  summary and `TEST-CRT-…` certificates (Al-Khair's newer certificate revoked), the self result of Al-Namaa, and the
  fictional generic maturity texts — only for generic bands whose text is still empty. Text a Content Officer wrote
  (generic or per main criterion) is never overwritten;
- the cast is resolved from `demo_records`: the three demo entity owners in install order (Al-Amal, Al-Namaa,
  Al-Khair — the same mapping as a fresh install) and the demo Assessor, System Administrator and Content Officer. A
  missing or deleted one refuses the run (`missing_prerequisite`). Entities the demo did not create are neither read
  nor changed and do not block the run;
- existing requests, drafts, self results, payments and receipts stay untouched. The demo requests are written as
  closed records (never in an open state), so an open accredited draft of the same entity does not conflict; the
  purchase rules for new orders are not applied to these historical TEST payments. A demo entity with an OPEN
  accredited payment order (created, pending or a bank transfer under review) refuses the run (`open_payment_order`)
  until that order is paid, cancelled or expired;
- no e-mail, SMS or in-app notification is sent; audit rows are written as in a fresh install (each demo request has
  one `assessment.created` row and one `start → completed` status row instead of the opened/draft history);
- the idempotency marker is in the database: every result it adds (and every result of a fresh install since
  Task-71) is recorded in `demo_records` with kind `insights_assessment`. A second run is refused
  (`insights_already_installed`, "already installed; nothing was changed") and writes nothing;
- guards as `--content-only`: `APP_DEMO_DATA=true`, the demo posture outside `local` / `testing`, an installed demo.
  It cannot be combined with `--content-only` or `--accounts-file`. Prerequisites: a published criteria version and the
  accredited price (unchanged; payment channels and prices are not touched).

The output is one line, for example `Demo excellence-tools cases installed: accredited results: 4, revoked
certificates: 1, assessor summaries: 1, self results: 1, maturity texts filled: 4.` On a server, take a backup first,
then run it as the environment user from the current release:
`sudo -u moashir-<env> php8.4 /var/www/moashir/<env>/current/backend/artisan moashir:demo:install --insights-only`.
The certificates are issued with today's date (`issued_at`) although the results carry their historical completion
dates.

## Mail posture (SEC-02)
`Services\Platform\MailerPosture` runs at boot. In `APP_ENV=production` without the demo opt-in (`MOASHIR_DEMO` not
`true`) a default mailer that keeps e-mail on the server — transport `log` or `array`, or a `failover` / `roundrobin`
mailer containing one — is replaced by the `settings` mailer and `platform.mailer_refused` is logged as critical. E-mail
then goes through the platform SMTP settings or fails closed: `SettingsMailTransport` throws, the queued notification is
retried and INT-04 alerts the System Administrators; nothing is written to a log. Staging, local development and the
demo posture keep the configured mailer.

## Health and readiness
- `GET /api/v1/health` — static liveness (unchanged). The framework route `/up` is removed (never proxied).
- `GET /api/v1/health/ready` — real readiness. Anonymous callers get only `{ status }`: `ready` (200), `degraded` (200, a
  non-critical check fails) or `not_ready` (503, a critical check fails). Callers whose address is in
  `HEALTH_ALLOWED_IPS` (addresses or CIDR) and signed-in System Administrators (`platform.readiness.details` →
  `settings.general.manage`) also get `checks: [{ name, critical, status: ok | failing | skipped }]` and `checked_at`;
  never a host name, version, error text or path. Checks (`Contracts\Platform\ReadinessCheck`, tagged in
  `AppServiceProvider`): `database` (`select 1`), `redis` (PING, skipped when neither cache nor queue uses Redis),
  `queue` (heartbeat written by the scheduled `RecordQueueHeartbeat` job every minute, failing after 5 minutes; skipped
  with the `sync` queue), `disk_space` (free space of `storage/` ≥ `HEALTH_MIN_FREE_DISK_MB`) — critical; `malware_scanner`
  (clamd `PING`), `pdf_renderer` (pinned binary usable) — non-critical, skipped with the local fakes. A failing check is
  logged as `platform.readiness_check_failed` (check name and exception class only).
- Not proxied framework routes: Horizon runs behind `web`, `session.timeouts`, `mfa`, `password.current` and its gate
  (Super Admin only); the local disk no longer serves `storage/{path}` (`'serve' => false`).
- `backup:monitor` runs daily at 06:00; an unhealthy backup (`UnhealthyBackupWasFound`) or a failed run
  (`BackupHasFailed`) raises **INT-06** "Backup check failed" (in-app + e-mail) to every System Administrator, at most
  once per alert cool-down (`Listeners\Platform\AlertAdministratorsOfBackupProblem`).

## Audit log API (read-only, Task-51; screen in Task-37d)
- `GET /api/v1/audit-logs` (`audit.view`, System Administrator; Super Admin by bypass) — newest first, paginated
  (`per_page` ≤ 100). Filters: `filter[from]`, `filter[to]` (`YYYY-MM-DD`, whole days in Asia/Riyadh), `filter[actor_id]`,
  `filter[event]`, `filter[subject_type]` (snake_case model name, e.g. `entity`, `user`, `setting`), `filter[subject_id]`,
  `filter[ip]`. Row: `{ id, event, actor{ kind: user | super_admin | system, id, name, roles[] }, subject{ type, id },
  old_values, new_values, reason, tags[], ip_address, user_agent (≤ 255), url, request_id, created_at }`.
- `GET /api/v1/audit-logs/{id}` — one row (404 when hidden).
- `POST /api/v1/audit-logs/export` (`audit.export`, `throttle:exports`) — same filters, `reason` (10..1000, required from
  the Super Admin: 422 `reason_required`); streams a UTF-8 CSV (BOM, formula cells neutralised) of at most
  `moashir.audit.export_max_rows` (10,000) rows, else 422 `export.too_large`; the export itself is audited
  (`audit.exported`, row count and filters, tag `export`).
- Scope: the Super Admin sees everything. Other readers never see rows whose subject is a Super Admin account (same
  invisibility as `/users`), and a row performed by a Super Admin shows `actor: { kind: "super_admin", id: null, name:
  null }` (what was done stays visible, who did it does not). Values are never secrets: keys such as `password`,
  `*secret*`, `*token*`, `api_key`, `two_factor*`, `recovery*`, `signature`, `bearer*`, `credential*` are shown as
  `[redacted]`, and token path segments in values and URLs are redacted (SEC-17). The audit rows stay append-only (no
  write endpoint; DB triggers and grants unchanged).

## Checkout script and SRI (SEC-11)
`checkout.script { url, integrity }` is added to every open checkout (`MOYASAR_FORM_SCRIPT_URL`,
`MOYASAR_FORM_SCRIPT_INTEGRITY`; a value that is not `sha256|384|512-<base64>` is dropped to `null`). Findings while
building it (2026-11-04): the Moyasar CDN serves `mpf/1.15.0` (the version of Moyasar's documentation) but answers 403 for
`mpf/2.3.0`, the version pinned in the SPA; and it sends no `Access-Control-Allow-Origin` header, so a browser cannot
apply SRI (`crossorigin="anonymous"`) to a script loaded from that CDN — the load would fail. The default is therefore the
1.15.0 URL without integrity. To enforce SRI, serve the pinned file from the SPA origin (e.g.
`/vendor/moyasar/1.15.0/moyasar.js`, same-origin requests pass the CORS check) and set both variables; SHA-384 of
`mpf/1.15.0/moyasar.js` as downloaded on 2026-11-04: `sha384-oqe0guC0MTOY4t+u48OQadqsLshSsqG5o7n/m4Vezbh5hSreN6eP5LVE0/QYlnTw`
(verify again before use). SPA side: Task-37d.

## Deployment notes

- Migration `2026_11_06_120000` (Task-71): the `demo_records_kind_check` CHECK accepts `insights_assessment` (marker of
  the excellence-tools demo cases). Reversible: `down()` deletes the `insights_assessment` rows and restores
  `entity | user`. No grant change, no new permission key, package or environment variable. On a demo installed before
  Task-64, run `moashir:demo:install --insights-only` after the deploy (section "Excellence-tools cases on an installed
  demo").

- Migrations `2026_11_05_140000` … `140200` (Task-61, entity sign-in by password or one-time code): `140000` deletes the
  open `login_second_factor` codes and replaces the `otp_codes_purpose_check` CHECK with `login, sign_in_code,
  verify_email, verify_phone, password_reset, email_change` (reversible: `down()` deletes the `sign_in_code` rows and
  restores the Task-51 CHECK). `140100` deletes the settings row `auth.entity_second_factor_enabled`; its `down()` is an
  intentional no-op (the previous code read a missing row as its default). `140200` clears `two_factor_secret`,
  `two_factor_recovery_codes` and `two_factor_confirmed_at` of every user with an `entity_id`, writes one `mfa_disabled`
  authentication event per cleared account (`meta.reason: entity_second_factor_cancelled`) and logs only the count
  (`identity.entity_authenticators_cleared`); it is irreversible by design (`down()` is a no-op, the secrets are not
  kept). No grant change, no new permission key, package or environment variable. New config keys under
  `moashir.auth.otp` (no env): `request_per_identifier_per_ten_minutes` (3), `request_per_ip_per_ten_minutes` (10),
  `sign_in_sms_per_hour` (100). New named limiter `otp-request`. Deploy the backend together with the Task-61 SPA:
  the new SPA calls `POST /auth/otp/request` and reads `code_channels`, the old SPA expects the second-factor step.

- Migrations `2026_11_04_160000` … `160300` (Task-51): `otp_codes` purpose `login_second_factor` (CHECK replaced);
  `authentication_events.identifier_type` / `identifier_hash` (+ CHECK and index; append-only unchanged, new rows only);
  `users.phone_index` (backfilled from the encrypted numbers in the migration, partial unique index of verified numbers);
  `entities.unified_number` (CHECK + partial unique index). Grants are table-level, so no grant change. Run
  `php artisan moashir:breached-passwords:import database/data/breached-passwords` (imports the 46,296 SecLists
  digests). New permission key: none (`settings.auth.manage` already exists; it is now hard-locked to the System
  Administrator). New environment variables (all optional): `MOASHIR_DEMO` (set by the deploy kit),
  `MOYASAR_FORM_SCRIPT_URL`, `MOYASAR_FORM_SCRIPT_INTEGRITY`, `HEALTH_MIN_FREE_DISK_MB`; removed: `AUTH_LOGIN_EMAIL_PASSWORD`,
  `AUTH_LOGIN_EMAIL_OTP`, `AUTH_LOGIN_SMS_OTP` (ignored if still present). The scheduler now also runs
  `RecordQueueHeartbeat` every minute and `backup:monitor` daily. A branding logo stored as SVG is no longer served
  (re-upload it as PNG).

- Migrations `2026_11_04_140000` … `140200` (Task-49): `terms_versions.title` / `published_at` (backfilled from
  `created_at`), nullable `effective_from` / `published_by_user_id` for drafts, the CHECKs, the one-draft index and the
  `terms_versions_published_guard` trigger; the `accessibility` row and key CHECK of `cms_pages`; the `privacy_request`
  enquiry type in the `contact_messages` CHECK (its `down()` turns such messages into `other`). No grant change, no new
  permission key, package or environment variable. Add `php artisan moashir:legal:install-drafts --no-interaction` to the
  reference-data step of the deployment (`deploy/remote/activate-release.sh`, `install_reference_data`) or run it once by
  hand; on a demo, `moashir:demo:install --content-only` then publishes the drafts (section "Legal texts and pages").
- Backups (`config/backup.php`, approved 03 §F "daily encrypted backups, 30-day retention"): `backup:run` daily at 01:30,
  `backup:clean` daily at 01:00 keeps every backup of the last 7 days and one per day for the next 23 days, with no weekly,
  monthly or yearly archives, so no backup is older than 30 days (the newest backup is never deleted, and the 5000 MB cap
  still removes the oldest first). Off-server, in-Kingdom storage is still required before real operation
  (`deploy/RUNBOOK.md`).
- The review / demo deployment (provisioning, Nginx, PHP-FPM, Horizon, scheduler, ClamAV, PDF renderer, releases,
  rollback, demo data, uninstall) is scripted in the repository folder `deploy/`; the procedure is `deploy/RUNBOOK.md`.
- Migrations `2026_11_03_130000` … `130200` (Task-42): `contact_messages` and `contact_message_entries` (with the no-update
  trigger on entries), the `contact_message_purge` / `contact_message_erasure` housekeeping tasks, and the certificate
  suspension (`certificates.suspended_at` / `suspended_reason`, status CHECK with `suspended`, the rewritten
  `moashir_guard_certificates()` trigger function, verification outcome `under_review`). Default privileges cover the two
  new tables (no grant change; `moashir_app` needs UPDATE on `contact_messages` and DELETE on both tables for the purge).
  Run `php artisan moashir:permissions:sync` after the migration (new key `contact.messages.manage`, granted to the System
  Administrator and Content Officer roles). The scheduler runs `moashir:contact-messages:purge-closed` daily at 03:45.
  The certificate migration is reversible only while no certificate is suspended. No new package or environment variable.
- Migrations `2026_11_03_120000` … `120200` (Task-39): `stored_files` purpose `certificate_pdf`, `certificates` (with its
  guard trigger and the partial unique index of issued certificates) and `certificate_verification_logs`. Re-run the
  `certificates` and `certificate_verification_logs` lines of `database/sql/production-roles.sql` (certificates
  SELECT/INSERT/UPDATE, logs SELECT/INSERT/DELETE). New packages `chrome-php/chrome` 1.16.1 and `bacon/bacon-qr-code`
  3.1.1 (operator approval Session 24). New environment variables `PDF_CHROME_BINARY`, `PDF_RENDER_TIMEOUT_SECONDS`;
  `PDF_RENDERER=chromium` on the server. Horizon must run (certificate issuance on completion and certificate PDFs are
  queued); the scheduler runs `moashir:certificates:prune-verification-logs` daily. No new permission key.
- Migrations `2026_11_03_100000` … `100300` (path distinction): `user_assessor_qualifications` (with the backfill described in
  "Path distinction"), `user_request_paths`, `notifications.path` (+ CHECK), and the copy of the shared upload settings into both
  paths. Default privileges cover the two new tables (no grant change). No new permission key, package or environment variable.
  The demo installer and the local staff seeder qualify their assessor for both paths.
- Migration `2026_11_02_120000_create_demo_records_table` (`demo_records`: kind `entity | user`, record id, unique pair).
  No grant change (default privileges cover it).
- Horizon defines a `staging` environment; `HORIZON_MAX_PROCESSES` caps the workers on a shared server.
- `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`,
  `php artisan config:cache route:cache event:cache`, `php artisan horizon:terminate` after release.
- The scheduler runs `moashir:expire-invitations`, `moashir:prune-otp-codes`, `auth:clear-resets`,
  `moashir:assessments:sweep-criteria-update-holds` and `moashir:assessments:open-paid-requests` (every 10 minutes),
  `moashir:assessments:flag-overdue` and `moashir:assessments:purge-evidence-bundles` (hourly),
  `moashir:assessments:expire-completion-requests` and `moashir:award:advance-cycles` (every minute),
  `moashir:award:remind-submission-deadline` and `moashir:award:flag-overdue-committee` (hourly), `ExpirePaymentsJob` (every minute),
  `ReconcilePendingPaymentsJob` (every 5 minutes), `moashir:files:scan-pending` (every 5 minutes),
  `moashir:files:purge-orphans` (daily at 03:00), `backup:clean` and `backup:run`.
- Every deployment, after `migrate --force`: `php artisan moashir:permissions:sync` (applies the code-defined permission
  catalog; idempotent, keeps administrator edits, grants new keys to their default roles). Seeding is refused in
  staging and production, so `db:seed` is never part of a deployment.
- First deployment: `php artisan moashir:breached-passwords:import database/data/breached-passwords/curated-seed.sha256 --format=sha256`,
  then load the full breached-password list with the same command (section "Breached-password list"), and run
  `php artisan moashir:criteria:install-seed` to publish criteria version 1 (idempotent, safe on later deployments),
  `php artisan moashir:regions:install-seed` to install the regions and cities (idempotent), and publish the client's
  terms and privacy texts with `php artisan moashir:terms:publish` or from the back office after legal review of the
  drafts stored by `moashir:legal:install-drafts` (registration answers 503 until terms exist).
- `database/sql/production-roles.sql` makes `entity_profile_changes` SELECT/INSERT/DELETE-only for `moashir_app` (the
  trigger accepts DELETE only inside the permanent-deletion transaction); re-run its `REVOKE` / `GRANT` lines where the
  script already ran.
- `database/sql/production-roles.sql` makes `criteria_edit_events` INSERT/SELECT-only for `moashir_app`, like the audit
  tables; where the script already ran before this table existed, re-run its `REVOKE` / `GRANT` lines.
- Destructive database commands (`migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`)
  are refused in staging and production. Migrations are forward-only there: undo a change with a new migration, and
  restore from backup for data recovery.
- `database/sql/production-roles.sql` makes `assessment_status_transitions` and `assessment_result_snapshots`
  SELECT/INSERT/DELETE-only and lets `moashir_app` update only `assessment_scores.superseded_at`; re-run its `REVOKE` /
  `GRANT` lines where the script already ran. A queue worker (Horizon) must run: criteria edits re-bind open drafts in
  the `RebindOpenAssessmentsAfterCriteriaEdit` queued listener, and restoring an entity moves its frozen drafts in the
  `RebindAssessmentsAfterEntityRestore` queued listener. Until that listener has run, the restored entity's drafts
  cannot be submitted (409 `assessment.criteria_update_in_progress`); a stopped worker therefore blocks those
  submissions until it is back or the job fails for good (then INT-05 is raised and the hold is released).
- Migration `2026_11_01_120000_add_criteria_update_hold_to_assessments_table` adds
  `assessments.criteria_update_pending_since` and its CHECK; no grant change is needed (the column is part of the
  updatable `assessments` table).
- Nginx: log `$uri` instead of `$request_uri` (at least for `location /api/v1/search`) so search terms, which may
  contain names or e-mail addresses, never reach the access log.
- Nginx proxies only `/api` and `/sanctum` to PHP-FPM; `/horizon` is not proxied (it is restricted to the
  Super Admin anyway). Pass `fastcgi_param HTTPS on` so HSTS and secure cookies apply.
- Migrations `2026_11_01_130000_create_integration_credentials_table`, `2026_11_01_130100_create_settings_table` and
  `2026_11_01_130200_add_criteria_update_redispatches_to_assessments_table`; the default privileges of
  `database/sql/production-roles.sql` already cover the two new tables (no grant change). Run
  `php artisan moashir:permissions:sync` to apply the hard lock of the four integration permissions.
- Migrations `2026_11_01_150000_add_quarantined_at_to_stored_files_table` (column `stored_files.quarantined_at`, a CHECK
  that only infected files are quarantined, a partial index of unattached files) and
  `2026_11_01_150100_create_housekeeping_runs_table`. Re-run the `housekeeping_runs` lines of
  `database/sql/production-roles.sql` (INSERT/SELECT only for `moashir_app`).
- Migration `2026_11_01_160000_create_assessment_criteria_moves_table` (append-only re-binding summaries, erasable only
  through the permanent-deletion cascade). Re-run the `assessment_status_transitions, assessment_result_snapshots,
  assessment_criteria_moves` `REVOKE` / `GRANT` lines of `database/sql/production-roles.sql` (SELECT/INSERT/DELETE only
  for `moashir_app`). Drafts moved before this migration have no recorded move (`criteria_update: null`).
- Never set `SMS_PROVIDER=fake` outside local; staging and production refuse it and log `platform.fake_adapter_refused`.
- Migrations `2026_11_01_170000` … `170700` (accredited columns and rewritten CHECKs on `assessments`, the scorer role, snapshot
  kinds and file purposes, `assessment_assignments`, `assessment_cv_entries`, `evidence_files`, `assessment_feedback_items`,
  `assessment_completion_requests`, `evidence_bundles`). Re-run the `assessment_feedback_items` and `assessment_assignments`
  lines of `database/sql/production-roles.sql` (column-level UPDATE only). The PHP `zip` and `fileinfo` extensions are
  required (evidence bundles, content checks). Horizon must run: the opening listener, the refund listener, the scans, the
  bundles and the due-date recompute are queued. Nginx `client_max_body_size` and PHP upload limits must allow
  the largest per-path `upload_max_file_mb` on `/api/v1/assessments/*/evidence` too.
- Migrations `2026_11_01_180000_add_expiry_outcome_to_assessment_completion_requests_table` (column `expiry_outcome`,
  existing rows set to `continue_as_submitted`; status widened to 20 characters with the two expired states; partial index of
  the open deadlines) and `2026_11_01_180100_add_declined_incomplete_to_accredited_assessments` (status CHECK). No grant
  change. Reversible only while no completion request has expired and no request is declined. The scheduler must run
  every minute for `moashir:assessments:expire-completion-requests`.
- Migrations `2026_11_01_190000` … `190400` (award cycles and regions, cycle members, initial assessors and reminders, the
  advanced columns and rewritten CHECKs on `assessments` with the snapshot kind `initial_assessment`, `filtering_decisions`, and
  the status-history trigger that accepts the delete of one advanced draft). Re-run the `filtering_decisions` lines of
  `database/sql/production-roles.sql` (SELECT/INSERT/DELETE only). The scheduler must run every minute for
  `moashir:award:advance-cycles`. No new permission key, no environment variable.
- Migrations `2026_11_02_100000` … `100500` (committee states in the `assessments` CHECKs and the cycle national / announcement
  columns, `cycle_committee_assessors`, `committee_assignments` with `committee_scores` and `committee_notes`, `committee_averages`
  with `assessments.committee_average_id` and `secretary_decisions`, `award_rankings` and `award_region_approvals`,
  `committee_discussions` and `committee_discussion_entries`). Re-run the `committee_averages, secretary_decisions` and
  `committee_scores, committee_notes, committee_discussion_entries` lines of `database/sql/production-roles.sql`. The scheduler must
  run `moashir:award:flag-overdue-committee` hourly; `moashir:award:advance-cycles` now also starts the committee assessment on its
  date. No new permission key, no environment variable. Reversible while no request is in a committee state.
- Task-64 (excellence tools wave 1, assessor summary, receipt VAT number): migrations
  `2026_11_05_160000_create_assessment_summaries_table` (append-only, reuses `moashir_guard_supersede_once()`) and
  `2026_11_05_160100_create_maturity_interpretations_table` (seeds the four empty generic rows); both reversible. Re-run
  the `assessment_summaries` lines of `database/sql/production-roles.sql` (column-level UPDATE on `superseded_at` only).
  Then `php artisan moashir:permissions:sync` (three new keys: `reports.insights.own.view`, `reports.insights.view`,
  `content.insights.manage`). New settings keys `insights.gap_thresholds`, `insights.maturity_bands`,
  `finance.vat_registration_number` read their defaults until saved. No new package or environment variable. After the
  deploy the System Administrator should set the VAT registration number; receipts issued before carry no row.
