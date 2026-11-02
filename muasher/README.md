# Muasher (مؤشر) — Front-end

Vue 3 (JavaScript) front-end for the Tamayuz Institutional Assessment Portal. Arabic-first
(`ar`/RTL default, dark theme default) with full English (`en`/LTR) support. All data flows through
`src/services/`: the MSW mock layer answers in local development, the Laravel API (`../backend/`) in the review,
staging and production environments (hybrid mode below).

Full environment contract: `references/00-ai-workflow.md`. Hard rules:
`references/04-guardrails.md`. Structure: `references/06-architecture.md`. Theme:
`references/07-design-system.md`.

## Requirements

Node `^22.18.0 || >=24.12.0` (developed on Node 24 LTS), npm 10+.

## Commands

| Command | What it does |
|---|---|
| `npm install` | Install dependencies (also copies the MSW worker to `public/`). |
| `npm run dev` | Vite dev server at `http://localhost:5173` (MSW mocks active). |
| `npm run build` | Production build to `dist/`. |
| `npm run icons:pro` | With `FONTAWESOME_NPM_AUTH_TOKEN` exported: installs the pinned Font Awesome Pro package without touching the lockfile. |
| `npm run preview` | Serve the production build at `http://localhost:4173`. |
| `npm run lint` | oxlint + ESLint (auto-fix). |
| `npm run format` | Prettier over `src/` and `e2e/`. |
| `npm run test` | lint + unit tests. |
| `npm run test:unit` | Vitest (single run). `npm run test:unit:watch` for watch mode. |
| `npm run test:e2e` | Playwright (run `npx playwright install chromium` once first). |
| `npm run test:e2e:integration` | Playwright smoke against a real local backend (see "Real integration check"). |

## Environment

Copy `.env.example` to `.env.local`. Never commit values.

| Name | Meaning |
|---|---|
| `VITE_API_BASE_URL` | Empty: the MSW mock layer answers `/api/v1` and `/sanctum` (local development). Set (for example `/api/v1` behind the same Nginx origin): the real API is used. The CSRF cookie is read from the same origin. |
| `VITE_DEMO_MODULES` | Only with `VITE_API_BASE_URL`. Comma-separated module keys that stay on demo data (hybrid mode, see below). Empty: every module uses the real API and the mock layer is never loaded. |
| `VITE_DEMO_LOGIN` | `true` shows the demo quick-login on the login page. It works only in `npm run dev` (`import.meta.env.DEV`); every `vite build` (staging and production) drops the component, its strings and its mock endpoint. `.env.development` sets it for local development. |
| `VITE_SENTRY_DSN`, `VITE_SENTRY_ENVIRONMENT`, `VITE_SENTRY_RELEASE` | Self-hosted GlitchTip. |
| `VITE_FONTAWESOME_KIT_ID`, `FONTAWESOME_NPM_AUTH_TOKEN` | Font Awesome Pro (see below). Both optional at build time; the token is never part of a build. |
| `MOASHIR_API_PROXY_TARGET` | Shell variable for `npm run dev` / `npm run preview` only: proxies `/api` and `/sanctum` to a local Laravel (for example `http://127.0.0.1:8010`) so the SPA and the API share one origin, as behind Nginx. |

## Hybrid mode (review and demo environments)

The review / demo deployments (operator decision Session 20) run the real API for every module that has a backend
and keep demo data, clearly marked, for the modules that do not have one yet.

| `VITE_API_BASE_URL` | `VITE_DEMO_MODULES` | Result |
|---|---|---|
| empty | ignored | full mock layer (local development, unit and e2e tests) |
| `/api/v1` | empty | fully real; the mock code is never loaded |
| `/api/v1` | `bag,kpis,…` | hybrid: MSW starts with the handlers of the listed modules only and `onUnhandledRequest: 'bypass'`, so every other call reaches the real API |

- Module keys: `src/constants/mockModules.js`; the registry that maps each key to its handlers is
  `src/mocks/handlers/index.js` (`mockModules`). Unknown keys are ignored (reported to GlitchTip as an info event). The
  session and sign-in mocks are never served in hybrid mode.
- Hybrid mode needs a same-origin API path (`/api/v1`): the demo handlers match paths of the SPA origin.
- Demo handlers see the real signed-in user: before answering they read `GET /api/v1/auth/session` from the backend
  (cached 5 seconds, refreshed after sign-in and sign-out) and map it to a demo persona of the same role (an entity
  sees the demo entity). Random mock failures are off in hybrid mode.
- Every screen of a demo module shows the translated "Demo data" note (`DemoDataNotice`, route meta `demoModules`);
  demo parts embedded in connected screens (notification panel, assistant, footer and CMS copy, SMS / e-mail / cart
  campaigns) show the section variant. Connected screens show nothing.
- Current list (`.env.staging.example`, `.env.production.example`): `bag, certificates, kpis, cms, branding, dashboards,
  ai, notifications, notifications-admin, messaging, platform-settings` (no backend route yet) and `users, committee,
  secretary, results` (backend exists, the SPA batch is not aligned yet). Remove a key when its batch lands.

### Environment builds

```
cp .env.staging.example .env.staging.local        # add VITE_SENTRY_DSN / VITE_SENTRY_RELEASE there (never committed)
npm run build -- --mode staging
cp .env.production.example .env.production.local
npm run build -- --mode production
```

`*.local` files are ignored by version control. `VITE_DEMO_LOGIN` is `false` in both templates (and every `vite build`
drops the demo login anyway). `VITE_FONTAWESOME_KIT_ID` is empty in both: without the Pro package and without a kit the
build succeeds and icons are simply not drawn (icon-only buttons keep their accessible names).

### Real integration check (local backend)

The smoke suite `e2e/integration/*.spec.js` runs against a real local Laravel and is excluded from `npm run test:e2e`.
It uses its own database (default `moashir_integration`, never `moashir` or a test database) and the fictional local
staff accounts of the backend seeders (e-mail code sign-in, then authenticator enrolment).

```
createdb moashir_integration                      # once
./scripts/integration/backend.sh setup            # migrate + seed (APP_ENV=local, fake scanner / PDF / SMS, mail to stderr)
./scripts/integration/backend.sh serve  > /tmp/integration-serve.log 2>&1 &
./scripts/integration/backend.sh queue  > /tmp/integration-queue.log 2>&1 &
cp .env.staging.example .env.staging.local && npm run build -- --mode staging
MOASHIR_API_PROXY_TARGET=http://127.0.0.1:8010 npx vite preview --mode staging --port 5180 --strictPort &
MOASHIR_INTEGRATION_MAIL_LOGS=/tmp/integration-queue.log,/tmp/integration-serve.log npm run test:e2e:integration
```

Run `./scripts/integration/backend.sh reset` before every repeated run (the staff authenticators are enrolled once per
database), then restart the queue worker. The suite reads one-time codes from the mail log files only; it never prints
them. It covers sign-in with MFA enrolment and challenge, criteria, entity registration and profile, self assessment
draft → submit → result, the accredited price and checkout up to the gateway boundary (`503 integration.not_configured`
without payment keys), the follower queue, integrations, award cycles, the demo markers, and a sweep of every connected
administrator and entity screen that fails on any 4xx/5xx or error state.

## API contract and session

- Sanctum SPA cookies only: no token or user object is stored in the browser. Before the first
  state-changing call the client requests `GET /sanctum/csrf-cookie`; axios sends `X-XSRF-TOKEN`,
  `withCredentials` and `Accept-Language` (the active locale) on every request. A 419 refreshes the
  CSRF cookie once and retries; a second 419 or a 401 ends the session and returns to the login page.
- Sign-in follows the `step` field of the login response: `otp_required` → `/auth/otp`,
  `mfa_challenge` / `mfa_enrollment` → `/auth/mfa`, `authenticated` → the workspace.
- `src/api/caseMapping.js` is the only camelCase ⇄ snake_case boundary. Every request body and query
  key is sent in snake_case and every response is read back in camelCase; status values travel as the
  canonical backend value (`under_review`) and are shown through `status.<canonical value>` labels.
  The criteria enums `evaluation_type` (`guiding_points`) and `scale_kind` (`of_10`) are bridged the
  same way to the internal tokens (`guiding-points`, `of-10`); other enums (`group`, `scope`, problem
  codes) are used with their canonical values.
- Criteria weights are two-decimal strings (`"133.34"`). `src/utils/weight.js` does every sum,
  remainder and equal split in hundredths, so no float arithmetic touches a weight.
- Language: guests keep their choice in the `locale` cookie (the backend reads it as plain text);
  when signed in, a switch is also saved with `PATCH /api/v1/me/preferences`, and after sign-in the
  profile language from `GET /auth/session` is adopted (`src/plugins/localePreference.js`).
- Permissions come from `GET /api/v1/auth/session` (`permissions`, `roles`, `is_super_admin`,
  `committee_appointments`). Routes, the sidebar, the header search and buttons check permission keys
  (`src/constants/routeAccess.js`, `src/constants/permissionKeys.js`); unknown keys are denied and no
  role bypasses the check.

## Mock layer (local development)

The MSW handlers go through `src/mocks/contract/http.js`, which enforces the CSRF header and speaks
snake_case on the wire. The mock "server" keeps its session in the `moashir_mock_session` cookie (it
exists only when mocks are on). Fixed mock-only values: password `Muasher@2026`, e-mail OTP `482913`,
authenticator code `246810`. Every mock e-mail uses an `example.sa` domain.

The criteria mocks (`src/mocks/handlers/criteria*.js`, `src/mocks/stores/criteria*.js`) mirror the
backend Criteria domain: version 1 is seeded from the backend seed content and published; drafts,
weight guards, equal distribution, the evaluation-type switch, publish with scope, text corrections,
the published tree and the public model criteria answer with the backend codes and shapes, and
STD notifications go to the recipient roles (never to the actor). The mock state lives in memory, so
a full page reload restores the seed. Mock accounts record the language of their first sign-in as
their profile language.

The entities mocks (`src/mocks/handlers/{entities,entityLifecycle,entityEmailChanges,privacyRequests,
regions}.js`, `src/mocks/stores/entit*.js`, `regionsStore.js`, `privacyRequestsStore.js`) mirror the
31 routes of the backend Entities domain: the 13 regions and 68 cities (generated from the backend
seed), the current terms, registration with the anti-enumeration decoy, OTP activation, the own profile,
the official e-mail change (`requires_approval`, with an approval queue), the staff list with record
scope (full view for the System Administrator and the Requests Follower, contact view for the Finance
Officer, nothing for other holders of `entities.view`), the lifecycle actions, deletion impact with a
stale-list scenario (entity `ENT-006002` gains an open request after its first impact read), restore,
Super Admin permanent deletion, the CSV and personal-data exports, data-subject requests and the city
list. A mock Super Admin session is a descriptor with `superAdmin: true`.

The integrations mocks (`src/mocks/handlers/{integrations,paymentIntegration,smsIntegration,emailIntegration,
aiIntegration}.js`, `src/mocks/stores/integrations/*`) mirror the real backend contract of
`agents/tasks/reports/Task-16-report.md` §5 (`backend/routes/api/v1/integrations.php`) field by field:
- Every route needs its own permission (`settings.payment.manage`, `settings.sms.manage`, `settings.email.manage`,
  `ai.provider.manage`); the overview lists only the kinds the session may manage, with `configured` per kind and
  `meta.payment_test_mode_in_production`.
- Every PUT is partial: an omitted field keeps its value, and a field sent for a provider without a stored row creates
  that row (as the backend does). The screens therefore send only what changed; when a kind still runs on the
  environment bootstrap (`source = environment`) they send every field, because the first save replaces the
  environment values and environment secrets cannot be copied.
- Secrets are write-only: the mock keeps only `{ set, last4, updated_at, updated_by }` and never the value. An omitted
  or empty secret keeps the stored one, a string replaces it, `{ "clear": true }` removes it. A changed key or public
  field resets that provider's `last_test`.
- A fake key that contains `revoked` fails the provider test with `auth_failed`, one that contains `inactive` with
  `account_inactive`; every other fake key passes. A failed test answers 502 `provider_error` with the full result in
  `meta.result`; a test of something not configured answers 409 `integration.not_configured`. Test, load and send
  actions allow 3 calls per minute per user and route, then answer 429 with `Retry-After`.
- Activation rules: payments need a complete pair with a passing test of the selected mode (also when switching to
  `live`); SMS, e-mail and AI need their required fields; AI also needs the provider's data transfer acknowledgement.
  `reason_required` comes from the Super Admin changing a secret or registering the webhook, and from production
  leaving live payments for test mode; the screens open the reason dialog on that answer and resubmit.
- The cookie `moashir_mock_env=production` makes the mock behave as production (`is_test_mode_in_production`, the
  overview banner flag and the reason required when payments leave live mode).
- SMS test numbers are Saudi mobiles only (`+9665XXXXXXXX`); in test mode a test SMS to any other number answers 422
  `sms.recipient_not_in_test_numbers`. The only template is `otp_code`. The approved Msegat templates use `{{1}}`
  placeholders and match the default texts with the server matching rules; any other text fails the check when the
  Msegat sender is template-restricted.

The self-assessment mocks (`src/mocks/handlers/assessments.js`, `src/mocks/stores/assessments/*`) mirror the 7 routes
of `backend/routes/api/v1/assessments.php` and the `requests` group of `GET /search`:
- Record scope as in `AssessmentRecordScope`: the entity reads its own assessments, the System Administrator, the
  Requests Follower and the Super Admin read all of them (frozen ones only with `filter[entity_id]`), every other holder
  of `assessment.history.view` gets an empty list and 404 on a record. Writes are for the owning entity only (403 for
  read-only staff, 404 for other entities); the Super Admin must send a `reason`.
- Fixtures: `SLF-2026-000001` (result) and `SLF-2026-000002` (open draft) of `ENT-005001`, `SLF-2026-000003` (result) of
  `ENT-006002`, `SLF-2026-000004` (open draft of the deleted entity `ENT-007002`, frozen) and `SLF-2026-000005` (open
  draft of `ENT-007005` held by a pending criteria update, submission answers 409
  `assessment.criteria_update_in_progress`).
- Scores are raw values on the assessment scale; the mock computes percents, earned scores and results with the same
  integer fixed-point rules as the backend `ScoringEngine` (`selfScoring.js`). The SPA never computes them.
- A criteria publication with scope `all` or `reset_open` re-binds the open drafts of operational entities
  (`criteriaEditHooks.js`, `affected_open_count` is real). Restoring an entity holds its open drafts and a queued job
  (`mockQueue.js`, 1.5 s) moves them per the strictest scope of the missed publications, then releases the hold.
- `GET /assessments/{id}` adds `status_history` (oldest first, `actor_staff` only for staff readers, the Super Admin
  hidden as `{ name: null, roles: [] }`); `GET /assessments/{id}/form` adds `criteria_update` (the latest move with
  kept / reset / added items and their labels, or `null`).

The accredited mocks (`src/mocks/handlers/{accredited,requests,assessorReview,assessmentSettings,servicePrices}.js`,
`src/mocks/stores/accredited/*`) mirror the accredited routes of `assessments.php`, `requests.php`, `/settings/assessment`
and the public `GET /content/prices` (Task-24 and Task-25 reports):
- Accredited requests live in the shared assessment store (path `accredited`, numbers `ACC-YYYY-######`); CV entries,
  evidence and feedback are keyed by the sub-criterion code and answered with the ids of the bound version.
- A paid accredited payment opens a draft (queued, idempotent per payment; a lost job is repaired by the sweep that runs
  before every accredited answer). `POST /payments` answers 409 `accredited.request_open` / `payment_being_processed`;
  a recorded refund closes an open request as `refunded`.
- Evidence is sniffed by magic bytes against the allowed types of the settings, stored `pending` and scanned by a queued
  job (2.5 s); a file containing the EICAR test string becomes `infected` and the entity gets EVD-02. Non-owners see clean
  files only; downloads use the signed links of the finance file store. Bundles above 2 MB (backend 20 MB) are prepared
  in the background (2.5 s) and announced by EVD-01.
- The completion deadline is applied before every accredited answer (`expire_completion`, `continue_as_submitted` →
  `under_review`, `decline` → `declined_incomplete`, ACC-12).
- Fixtures: `ACC-2026-000001` (completed, `ENT-005001`), `000002` (refunded, `ENT-006002`), `000003` (completed) and `000004` (draft with
  a pending and an infected file, three missing items), both of `ENT-006001`, `000005` (submitted, overdue), `000006`
  (completion requested of `ENT-006004`), `000007` (assigned to assessor 10, overdue), `000008` (under review by assessor
  10, reassigned once).

The award mocks (`src/mocks/handlers/award.js`, `src/mocks/stores/award/*`) mirror `backend/routes/api/v1/award.php` and the
advanced routes of `assessments.php` / `requests.php` (Task-27 report §2):
- Cycles are seeded relative to today so the running cycle always accepts applications: cycle 30 (closed and announced),
  31 (submissions open, regions Riyadh / Makkah / Madinah / Eastern, filtering officers 13 and 8, secretary 18, initial
  assessors 10 / 11 / 15) and 32 (a draft prepared while 31 runs). Opening, closing the submissions (drafts expire) and the
  close (refused while requests are open) follow the backend state machine; a scheduler step runs before every answer.
- Advanced requests live in the shared assessment store (path `advanced`, numbers `ADV-YYYY-######`) and reuse the
  accredited form, CV, evidence, queue, assignment, review and completion-expiry engine through
  `stores/assessments/reviewedPaths.js`; they stay on the criteria version of their cycle.
- The entity sees `under_review` for `awaiting_filtering`, `qualified_for_committee` and `not_qualified` until the cycle is
  announced (`stores/award/statusMask.js`); an assessor who does not read results sees `awaiting_filtering`.
- Seeds of cycle 31: `ADV-YYYY-000001` (`ENT-006001`, returned for completion), `000002` (`ENT-006002`, under initial
  assessment by assessor 10), `000003` (`ENT-006004`, awaiting assignment), `000004` (`ENT-006005`, awaiting filtering above
  the threshold), `000005` (`ENT-007005`, awaiting filtering below the threshold), `000006` (`ENT-006006`, region not
  covered, conditions rejection); `ENT-005001` has no request in cycle 31 and a not-qualified request in cycle 30.
- The committee, secretary and results screens still run on the legacy committee mocks (`handlers/committee.js`,
  `handlers/awardResults.js`, `handlers/advancedResults.js`) until their backend contract (Task-28) is available.

The payments and finance mocks (`src/mocks/handlers/{payments,finance,financeSettings,moyasarSandbox}.js`,
`src/mocks/stores/finance/*`) mirror `backend/routes/api/v1/finance.php` and `settings.php`:
- Checkout A1: `POST /payments` needs a UUID `Idempotency-Key`; the order snapshots the price from
  `/settings/finance`, the gateway mode, methods and networks come from the payment integration mock, the checkout
  object carries the intent, the snake_case `metadata` and the callback `/payments/{id}/return`. An open order of the
  same service is returned with 200. Created orders expire after `order_validity_minutes`.
- No request ever reaches Moyasar: MSW serves the pinned `cdn.moyasar.com/mpf/2.3.0/moyasar.js` from
  `src/mocks/moyasar/fakeMoyasarForm.js` (a sandbox form with outcome choices and no card fields) and answers
  `api.moyasar.com/v1/payment_intents/{id}/fulfill`. `POST /payments/{id}/verify` reads that sandbox ledger and applies
  the backend rules (metadata binding, amount, 202 while 3-D Secure is pending, late payment stays cancelled).
- Receipts `RCP-YYYY-######` gap-free for live payments, TEST receipts without a number, voids on refund; downloads
  go through signed links (`/api/v1/files/{id}/download`, 5 minutes, bound to the user).
- Refunds: full amount only, PDF / JPG / PNG checked by magic bytes; a file containing the EICAR test string is refused
  as infected. Exports above 10 rows are queued (the backend queues above 5,000) and become ready after 4 seconds.
- The finance mock state (payments, receipts, refunds, files, exports) is kept in `sessionStorage`
  (`moashir_mock_finance`, outside unit tests) so the return page survives the full-page redirect; it never holds an
  intent client secret.
- Seeds: own payments of `ENT-005001` (paid live, TEST paid, failed, pending bag order), staff fixtures for every status
  including a late payment, a recorded refund and a pending live order to settle manually.

Random mock failures (the deliberate 5xx of the older handlers) go through
`src/mocks/contract/faults.js`:
- They are ON in local development, OFF in unit tests (`import.meta.env.MODE === 'test'`) and OFF in
  e2e runs (`playwright.config.js` seeds the `moashir_mock_faults=off` cookie through `storageState`).
- A unit test can switch them on with `setRandomFaults(true)` or force one endpoint to fail with
  `forceMockFault({ method, path, status })`; `clearMockFaults()` resets both.
- An e2e test forces an error explicitly with `forceMockFault(page, { method, path, status })` from
  `e2e/support/mockFaults.js` (cookie `moashir_mock_fault`) and removes it with `clearMockFault(page)`.

## Icons (Font Awesome Pro, self-hosted)

Icons are Font Awesome Pro served from the build when the Pro package is present: its solid, regular
and brands webfonts are compiled by `src/styles/vendor/font-awesome.scss` and emitted into
`dist/assets/`, so no icon request leaves the platform. The package is NOT a dependency in
`package.json` / `package-lock.json`, so `npm ci` works in every clean checkout (CI, deploy) without the
token, and the lockfile never depends on the private registry.

With the token (pinned version, installed next to the locked tree, never saved):

1. Export `FONTAWESOME_NPM_AUTH_TOKEN` in your shell or CI secret store (never in a file of this
   repository). `.npmrc` reads it by name only.
2. `npm ci` then `npm run icons:pro` (`npm install --no-save @fortawesome/fontawesome-pro@7.3.1`).
3. `npm run build`. `vite-plugins/fontAwesome.js` detects the package and switches the app to the
   self-hosted icons; the CDN kit is no longer injected.

Without the token the build still succeeds: the app uses the kit of `VITE_FONTAWESOME_KIT_ID` when set,
otherwise no icon font is loaded (icons are not drawn; icon-only buttons keep their accessible names).

## Dependencies

- Every direct dependency is pinned to an exact version and `.npmrc` sets `save-exact=true`, so
  `npm install <pkg>` keeps the pin. `package-lock.json` pins the transitive tree.
- `npm audit` must report 0 vulnerabilities before every release.
- `overrides` in `package.json` force two transitive packages to their first patched major,
  because no in-range fix exists yet:
  - `minimatch` 11.0.3 (GHSA-g3hv-75wg-m249; consumers eslint, `@eslint/config-array`,
    `@typescript-eslint/typescript-estree`, editorconfig, glob). They only use `new Minimatch().match()`
    and `minimatch()`, which minimatch 11 keeps.
  - `glob` 14.0.3 (GHSA-3g3m-2fjr-6pw4; only consumer js-beautify under `@vue/test-utils`, loaded
    by its CLI only).
  Remove an override once every consumer declares a range that already includes the patched
  version, then run `npm install` and `npm audit`.
