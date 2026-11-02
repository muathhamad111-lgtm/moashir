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
   clamd or PDF library, `FILES_SCANNER=fake` and `PDF_RENDERER=fake` (local only, see "Local-only fakes").
4. `composer install && php artisan key:generate && php artisan migrate --seed`
5. `php artisan serve` and, in another terminal, `php artisan queue:work` (OTP, invitations and alerts
   are queued; with `MAIL_MAILER=log` the messages appear in `storage/logs/laravel.log`).

`migrate --seed` loads the code-defined permission catalog (83 keys, 9 roles), the curated breached-password
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
- **Full list** — the operator loads a larger freely licensed list with the import command. Recommended source:
  SecLists (MIT licence), `Passwords/Common-Credentials/`, e.g. `xato-net-10-million-passwords-1000000.txt`
  (check the provenance note of the chosen file in the SecLists README before loading). Download it on an operator
  workstation, copy it to the server over the approved secure channel, then run:

  ```
  php artisan moashir:breached-passwords:import /path/on/server/list.txt              # one password per line
  php artisan moashir:breached-passwords:import /path/on/server/list.sha256 --format=sha256   # one hex digest per line
  ```

  The command streams the file in chunks of 1,000 lines, skips blank lines, ignores entries already listed (safe to
  re-run) and reports `read / added / already listed / rejected`. Delete the plain-text file from the server after
  the import. The large list is never committed to the repository. The Have I Been Pwned dataset is not used
  (operator decision 2026-10-31: no Have I Been Pwned and no external service).

## Environment variables

`.env.example` lists every name with no value. Variables specific to Moashir:

| Name | Purpose |
|---|---|
| `FRONTEND_URL` | Base URL of the SPA used in e-mailed links (invitation, password reset) |
| `CORS_ALLOWED_ORIGINS` | Comma-separated SPA origins (same origin in production) |
| `SANCTUM_STATEFUL_DOMAINS` | Hosts allowed to use the session cookie |
| `SESSION_IDLE_SUPER_ADMIN_MINUTES`, `SESSION_IDLE_STAFF_MINUTES`, `SESSION_IDLE_ENTITY_MINUTES`, `SESSION_ABSOLUTE_HOURS` | Idle (15 / 30 / 60) and absolute (12 h) session timeouts |
| `AUTH_LOGIN_EMAIL_PASSWORD`, `AUTH_LOGIN_EMAIL_OTP`, `AUTH_LOGIN_SMS_OTP` | Enabled sign-in modes |
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
| `TAQNYAT_API_TOKEN`, `TAQNYAT_SENDER`, `MSEGAT_USERNAME`, `MSEGAT_API_KEY`, `MSEGAT_SENDER` | SMS bootstrap (optional) |
| `AI_PROVIDER`, `AI_API_KEY`, `AI_MODEL` | AI provider bootstrap (optional, `openai` or `anthropic`) |
| `SERVER_EGRESS_IPS` | Comma-separated public egress addresses of the server, shown on the settings screens for the providers' IP allow-lists |
| `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_DISK`, `BACKUP_NOTIFICATION_MAIL` | Encrypted off-server backups |
| `HEALTH_ALLOWED_IPS` | Reserved for the internal readiness probe (next step) |
| `FILES_DISK` | Laravel disk of uploaded and generated files (default `local` = `storage/app/private`, outside the web root) |
| `FILES_SCANNER` | `fake` marks every file clean except the test signature; honoured in `local` and `testing` only and refused elsewhere (ClamAV is used and `platform.fake_adapter_refused` is logged as critical); unset = ClamAV |
| `FILES_CLAMAV_SOCKET`, `FILES_CLAMAV_TIMEOUT_SECONDS` | clamd address (`unix:///var/run/clamav/clamd.ctl` or `tcp://127.0.0.1:3310`) and timeout (30 s) |
| `FILES_ORPHAN_GRACE_HOURS` | Age after which a stored file never attached to an owner is purged (default 24 hours, see "File housekeeping") |
| `PDF_RENDERER` | `fake` renders placeholder PDFs; honoured in `local` and `testing` only and refused elsewhere (logged like `FILES_SCANNER`); unset = no renderer yet, receipt downloads answer 503 `document.renderer_unavailable` |
| `ASSESSMENT_SCALE_KIND` | Assessment scale copied into a criteria version when it is published: `of_10` (default) or `relative`; seam until the Settings domain stores it |
| `ENTITY_EMAIL_CHANGE_POLICY` | Official e-mail change of an entity: `requires_approval` (default) or `immediate_notify` (Q-103); seam until the Settings domain stores it |
| `ENTITY_VERIFICATION_REMINDER_COOLDOWN_MINUTES` | Minimum time between two verification reminders of the same unverified registration (default 15) |
| `APP_DEMO_DATA` | `true` allows `moashir:demo:install` and `moashir:demo:rotate-passwords` (review / demo environments only, see "Demo data") |
| `SESSION_COOKIE` | Session cookie name; distinct per environment when two environments share a parent domain |
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
| GET | `/auth/login-modes` | public |
| POST | `/auth/login` (`mode: password \| otp`) | public, `throttle:login` (5/min per e-mail + IP) |
| POST | `/auth/otp/request`, `/auth/otp/verify`, `/auth/otp/resend` | public, OTP limiters |
| POST | `/auth/mfa/challenge` | pending sign-in, `throttle:mfa` |
| POST | `/auth/mfa/enroll`, `/auth/mfa/confirm` | pending enrolment or signed in |
| POST | `/auth/password/forgot`, `/auth/password/reset` | public, `throttle:password-reset` |
| POST | `/auth/invitations/{token}/accept` | public, `throttle:invitation` |
| GET | `/auth/session` | signed in |
| POST | `/auth/logout`, `/auth/password/change` | signed in |
| POST / DELETE | `/auth/mfa/recovery-codes`, `/auth/mfa` | signed in + current password |
| GET / PATCH | `/me/preferences` | signed in |
| GET / DELETE | `/me/sessions`, `/me/sessions/{session}` | signed in (own sessions only) |
| GET | `/me/profile/permissions` | signed in |
| POST | `/me/email/change`, `/me/email/confirm` | Super Admin only |
| GET / POST | `/users` | `users.view` / `users.manage` |
| GET / PUT | `/users/{user}` | `users.view` / `users.manage` |
| POST | `/users/{user}/suspend`, `/activate`, `/unlock`, `/invitation/resend`, `/mfa/reset` | `users.manage` |
| GET | `/roles` | `users.view` or `roles.matrix.view` |
| GET | `/permissions` | `roles.matrix.view` |
| PUT | `/permissions/{key}` (labels), `/roles/{role}/permissions` (matrix) | `roles.matrix.manage` |
| GET | `/search?q=&limit=` | `search.global` (any search provider available), `throttle:search` (60/min per user) |

Super Admin accounts answer 404 on every `/users/{user}` path for every caller and never appear in lists.

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
  consent and lifecycle timestamps, soft delete (`deleted_at` = "Deleted entities"), `anonymized_at` (erased tombstone).
  Every value is guarded by a CHECK constraint. The human reference is `ENT-` + the zero-padded id (`ENT-000014`).
- One login per entity: the single `users` row with `users.entity_id` (unique, now a foreign key). The authorized
  person's name, the official e-mail and the mobile number live on that user only (single source of truth).
- `entity_profile_changes`: one row per changed profile field (old and new value, actor). Append-only: the model
  refuses updates and deletes, a trigger rejects UPDATE and TRUNCATE, and DELETE is accepted only inside the
  permanent-deletion transaction (`moashir.entity_erasure` session flag). Only the business fields of
  `EntityProfileField` are recorded (name, classification, founding year, region, city, governance grade, counts,
  strategic plan); the authorized person, the official e-mail and the mobile number are never part of the history.
- `privacy_requests`: PDPL data-subject requests (`access | correction | deletion`, `open → fulfilled | rejected`).
- `email_change_requests` (Identity table) now also carries entity requests (`entity_id`).

### Registration and sign-in
`POST /auth/register` creates the entity (`pending_verification`) and its owner (role `entity`, password through
`PasswordPolicy` with the local breached list), records the acceptance of every CURRENT terms version (`terms` and,
when published, `privacy`), records the explicit marketing opt-in (unticked by default) and sends a sign-in OTP to the
official e-mail. The response is always `201 { step: otp_required, requires_otp, otp{challenge_id, …}, message }`;
an e-mail that is already used gets the same answer with a decoy challenge and nothing is created or sent
(anti-enumeration). Registration answers 503 `registration.terms_unavailable` while no terms version is published.

Verifying that OTP through the existing `POST /auth/otp/verify` activates the entity (REG-03 when the profile is
incomplete) and signs it in. A pending entity that signs in with its password receives a new OTP
(`step: otp_required`). A deactivated entity gets 403 `account_deactivated`; a deleted entity gets the generic
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
| GET / PUT | `/entities/me` | `entity.profile.view` / `entity.profile.edit` (own entity), PUT `throttle:sensitive` |
| POST | `/entities/me/email/change`, `/entities/me/email/confirm` | `entity.profile.edit`; `throttle:email-change` (3/10 min) / `throttle:otp-verify` |
| GET | `/entities/me/profile-changes` (`page`, `per_page`) | `entity.profile.view` (own entity) |
| GET | `/entities` (`search`, `filter[status|region_id|city_id|entity_type|development_sector|org_size|trashed]`, `sort`, pagination, `meta.stats`) | `entities.view` (+ `entities.delete` for `filter[trashed]=only`) |
| POST | `/entities/export` (same filters, CSV stream, audited, max 10,000 rows) | `entities.export` + full scope, `throttle:exports` (5/min) |
| GET / PUT | `/entities/{entity}` (PUT = core data on behalf, `reason` required) | `entities.view` / `entities.manage` |
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
php artisan moashir:terms:publish terms 2026-11 --ar=/path/terms-ar.txt --en=/path/terms-en.txt --publisher=<staff e-mail> [--effective-from=Y-m-d]
php artisan moashir:terms:publish privacy 2026-11 --ar=… --en=… --publisher=…
```

The terms texts are supplied by the client; the command makes the new version current and audits it
(`terms.published`). Authoring the texts in the back office belongs to the CMS domain.

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
| GET | `/assessments` (`filter[path|status|entity_id]`, `sort` = ±`created_at` / `submitted_at` / `completed_at`, pagination) | `assessment.history.view` | own entity; System Administrator and Requests Follower read every assessment (frozen ones only with `filter[entity_id]`) |
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
the Requests Follower queue, the assessor review and the Super Admin overrides. Advanced, Award, certificates, the
feedback-report PDF and the Kit are not part of this step.

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
  Assessment | null, latest_closed: Assessment | null }`. The waiting-for-payment phase is the open accredited payment order.
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
- `GET /requests` — submitted accredited requests of operational entities (drafts and frozen requests never listed):
  `filter[path]=accredited`, `filter[status]` (`submitted | assigned | under_review | needs_completion | completed |
  refunded | declined_incomplete`), `filter[overdue]=true|false`, `filter[assessor_id]`, `search` (request number, entity name or e-mail),
  `sort` ±`submitted_at | due_at | status | created_at` (default `-submitted_at`), pagination, `meta.stats { total, by_status,
  overdue }`. Row: `{ id, number, path, status, is_frozen, entity{ id, reference, name, status }, assessor{ id, name } | null,
  assigned_at, completion_deadline, submitted_at, due_at, is_overdue, completed_at }`.
- `GET /requests/{id}` — row + `payment { paid }`, `record` (the assessment with `status_history`), `assignment_history[{ id,
  assessor{ id, name }, status, assigned_at, started_at, ended_at, assigned_by{ id, name }, reason, end_reason }]`,
  `completion_requests[]`. The assigned assessor (`requests.assigned.view`) sees only their own assignment rows, without
  `assigned_by`, reasons or staff names.
- `GET /requests/assessors?assessment_id=&search=` — active staff holding `assessment.score.manage` (assessor role), never a
  Super Admin, never the caller (SoD-4), never the current assessor of the given request: `[{ id, name, open_requests }]`.
- `POST /requests/{id}/assign { assessor_id, reason? }` (`submitted → assigned`) and `POST /requests/{id}/reassign {
  assessor_id, reason 10..1000 }` (status kept; previous assignment `reassigned` with the reason). 422 `assessor.invalid`,
  `sod_self_assignment`, `assessor.same`; 409 on any other state. ACC-05 to the assessor (I+E) and the other Requests
  Followers (I+E); on reassign the previous assessor is told in-app and loses access at once. The Super Admin must give a
  reason (SEC-04).
- SLA: due date = submission + `assessment.accredited_review_sla_days` (default 14). A request is late when its due date is
  past and it is `submitted`, `assigned` or `under_review` (not while waiting for the entity). `moashir:assessments:flag-overdue`
  (hourly) sends ACC-11 once per due date to the assessor, the Requests Followers and the System Administrators. Changing the
  SLA dispatches `RecomputeAccreditedDueDatesJob` (unique until processing, idempotent): every open request with a due date
  moves by the difference between the new SLA and its `review_sla_days`, so a time already shifted by a freeze is kept; the
  paused due date of a frozen request is shifted the same way.

### Assessor work
- `GET /requests/assigned?filter[tab]=new|in_progress|completed` — the caller's requests (current or completed assignment),
  frozen ones excluded, ordered by due date: `{ id, number, path, status, entity{ …, region }, assigned_at, started_at,
  completion_deadline, submitted_at, due_at, is_overdue, completed_at }`.
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
  needs_completion`, ACC-07 entity I+E, whose text states the outcome) · `POST /assessments/{id}/complete` (all items scored —
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
`GET` → `{ accredited_review_sla_days, accredited_review_sla_limits { min: 1, max: 120 }, upload_max_file_mb,
evidence_allowed_types[], evidence_max_files_per_sub, evidence_type_catalog[], advanced_min_entity_age_years,
advanced_disqualification_score, advanced_initial_review_sla_days, committee_entity_data_visibility }` (the three advanced keys are the defaults copied into a new
award cycle: 3 years, "60.00", 14 days; 0..100 years, 0..100 with two decimals, 1..120 days). `PUT` is partial (same keys; types ⊂
`pdf, docx, xlsx, pptx, jpg, png`, at least one; file size 1..100 MB; 1..50 files per sub-criterion; `reason` 10..1000,
required for the Super Admin). Each changed key writes `settings.assessment_updated`. `upload_max_file_mb` is the shared
`files.max_size_mb` (refund evidence included). `committee_entity_data_visibility` (`name_only` default | `code_only` |
`full`, Q-57) decides what a committee assessor sees of the entity (see "Award committee").

### Lifecycle hooks
Open accredited requests are listed in the deletion impact, frozen with the entity (no write, out of every queue and of the
overdue sweep), their review due date and completion deadline are paused and resumed (Q-105), the restore hold and the
restore re-binding cover them, and a criteria publication re-binds them by scope (`reset_open` supersedes the assessor
scores and sends STD-08 to the entity and the assessor, I+E). Permanent deletion (`ReviewedRequestEraser`) deletes the
open (frozen) requests, every CV entry, completion request, evidence row and bundle, and detaches the evidence and bundle
files so the daily orphan purge removes them; completed results stay on the erased tombstone and the payments stay.

## Evidence files
- `POST /assessments/{id}/evidence` (multipart `file`, `sub_criterion_id`, `attachment_key?`, `reason?`; `throttle:uploads`
  30/min): owner, `evidence.manage`, state `draft` (or `needs_completion` for the requested items). The extension, the
  server-detected MIME type and the magic bytes must match one of the allowed types of the settings (PDF, DOCX, XLSX, PPTX,
  JPG, PNG by default; 422 `file.content_mismatch`); size ≤ `files.max_size_mb`; at most `files.evidence_max_files_per_sub`
  files per sub-criterion (422 `attachment.sub_full`, `meta.max_files_per_sub`); the key must be a slot of the sub-criterion
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
| GET | `/requests/assigned` | `can:viewAssignedRequests` (`requests.assigned.view`) |
| GET | `/requests/assessors` | `can:listAssessors` (`requests.assign`) |
| GET | `/requests/{assessment}` | `can:viewRequest` (queue view, or the assessor of the request) |
| POST | `/requests/{assessment}/assign`, `/reassign` | `can:assign` / `can:reassign` (`requests.assign`), `throttle:sensitive` |
| GET | `/assessments/{assessment}/review` | `can:review` (assignee with `assessment.score.manage`, or global reader with `requests.review.read`) |
| POST | `/assessments/{assessment}/start-review`, `/request-completion`, `/complete` | `can:score` (active assignee), `throttle:sensitive` |
| PUT | `/assessments/{assessment}/review/scores`, `/review/feedback` | `can:score`, `throttle:assessment-write` |
| POST | `/assessments/{assessment}/scores/override`, `/reopen`, `/transition/force` | `can:override` (Super Admin only), `throttle:sensitive` |
| GET / PUT | `/settings/assessment` | `can:settings.assessment-section.manage` (`settings.assessment.manage`), PUT `throttle:sensitive` |

Notification links (SPA routes): entity `/assessment/accredited`, `/assessment/accredited/result/{id}`,
`/assessment/accredited/result/{id}/feedback`; staff `/admin/requests/{id}`, assessor `/admin/requests/{id}/review`, bundle
`/admin/requests/{id}/evidence-bundles/{bundle}`. Audit events: `assessment.created`, `assessment.opened`,
`assessment.duplicate_payment`, `assessment.cv_saved`, `evidence.uploaded`, `evidence.removed`, `evidence.bundle_requested`,
`assessment.submitted`, `assessment.resubmitted`, `assessment.completion_expired`, `assessment.declined_incomplete`,
`assessment.assigned`, `assessment.reassigned`, `assessment.review_started`,
`assessment.scores_saved`, `assessment.feedback_saved`, `assessment.completion_requested`, `assessment.completed`,
`assessment.refunded`, `assessment.refund_after_completion`, `assessment.score_overridden`, `assessment.reopened`,
`assessment.transition_forced`, `assessment.due_dates_recomputed`, `settings.assessment_updated`.

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
- `award.cycles.view`: the System Administrator and the Requests Followers see every cycle; filtering officers and secretaries
  see the cycles they are members of; assessors the cycles they are linked to. Other cycles answer 404.
- `award.cycles.manage`: the System Administrator for every cycle; an award secretary for the cycles they are a secretary member
  of. Creating a cycle makes a creating secretary a member (a System Administrator is global and is not added).
- Members and initial assessors: candidates are active staff accounts holding `award.filtering.decide` (filtering officer),
  `award.secretary.decide` (secretary) or `assessment.score.manage` (initial assessor), never a Super Admin. Segregation: a
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
- The queue `GET /requests` lists both paths (`filter[path]=accredited|advanced`, `filter[cycle_id]`; rows add `award_cycle` and
  `region`). `GET /requests/assessors?assessment_id=` lists only the active initial assessors of the cycle who are not cycle
  members.
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
`TEST` or `VOID`. The Arabic-shaping library is not selected yet: `PDF_RENDERER=fake` locally, otherwise
`UnavailablePdfRenderer` (download 503 `document.renderer_unavailable`, the job defers). Reissue (`finance.receipts.manage`,
reason) renders a new file and keeps the previous one; a voided receipt cannot be reissued (409 `receipt.voided`).

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
evidence_bundle`), private disk
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
clamd, `UnavailablePdfRenderer`, and `SettingsSmsGateway`, so SMS is available only when the SMS settings are configured)
and `platform.fake_adapter_refused` is logged as critical with the setting name (`moashir.files.scanner`,
`moashir.documents.pdf_renderer`, `moashir.sms.driver`). The SMS decision is taken once per process
(`Data\Integrations\SmsDriverSelection`, a singleton shared by the gateway binding and `ConfiguredSmsAvailability`), so a
refused flag logs one line per worker, not one per sign-in. Set the flags in the local `.env` only.

### Payments and finance endpoints
| Method | Path | Ability / limiter |
|---|---|---|
| GET | `/payments/mine` (`filter[status|purpose]`, pagination, `meta.stats` live only) | entity + `payments.own.view` |
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

### Events and notifications
Events (after commit): `PaymentCreated` (PAY-01, entity, in-app), `PaymentPaid` (PAY-02, entity + Request Followers +
System Administrators), `PaymentFailed` (PAY-03, entity + System Administrators), `PaymentCancelled` (PAY-07 on expiry,
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
- Set the service prices with `PUT /api/v1/settings/finance` (System Administrator) and keep `PDF_RENDERER` unset until
  the PDF library is installed. Run `database/sql/production-roles.sql` again for the new grants (refunds and payment events
  INSERT/SELECT only, payments, receipts and exports without DELETE).

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
`phpunit.xml` sets `memory_limit` to 1G: the architecture tests parse every file of `app/`. See "Test isolation" below for how the suite is kept away from the development database.
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
demo terms and privacy texts when none are published, the accredited price when none is set, one draft award cycle
(created by the demo Award Secretary) and two TEST-mode payments (one paid with a TEST receipt, which opens the
accredited draft through the normal listener, and one open order). It never creates the Super Admin.

Guards (`Services\Demo\DemoInstallGuard`): refused unless `APP_DEMO_DATA=true`; refused when an entity exists that the
command did not create (the command records its entities and users in `demo_records`); refused when the demo is already
installed. Every refusal is logged as critical (`platform.demo_data_refused` with the reason only). Passwords are random
(24 characters) and are written only to the accounts file: a new file outside the application directory, created
exclusively with mode 600 before the database work and removed again if the installation fails. The console output
never contains a password. `php artisan moashir:demo:rotate-passwords --accounts-file=<absolute path>` issues new
passwords for every demo account, ends their sessions and writes a new file. The deployment kit wraps both commands
and moves the file to `/root/moashir-<env>-demo-accounts.txt` (see `deploy/RUNBOOK.md`).

## Deployment notes

- The review / demo deployment (provisioning, Nginx, PHP-FPM, Horizon, scheduler, ClamAV, releases, rollback, demo
  data, uninstall) is scripted in the repository folder `deploy/`; the procedure is `deploy/RUNBOOK.md`.
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
  terms and privacy texts with `php artisan moashir:terms:publish` (registration answers 503 until terms exist).
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
  `files.max_size_mb` on `/api/v1/assessments/*/evidence` too.
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
