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
| `npm run preview` | Serve the production build at `http://localhost:4173`. |
| `npm run lint` | oxlint + ESLint (auto-fix). |
| `npm run format` | Prettier over `src/` and `e2e/`. |
| `npm run test` | lint + unit tests. |
| `npm run test:unit` | Vitest (single run). `npm run test:unit:watch` for watch mode. |
| `npm run test:e2e` | Playwright (run `npx playwright install chromium` once first). |
| `npm run test:e2e:integration` | Playwright smoke against a real local backend (see "Real integration check"). |
| `npm run test:e2e:posture` | Playwright check of a real-operation build served on `:5180` (no mock worker, coming-soon screens; see "Build posture"). |

## Environment

Copy `.env.example` to `.env.local`. Never commit values.

| Name | Meaning |
|---|---|
| `VITE_API_BASE_URL` | Empty: the MSW mock layer answers `/api/v1` and `/sanctum` (local development). Set (for example `/api/v1` behind the same Nginx origin): the real API is used. The CSRF cookie is read from the same origin. |
| `VITE_DEMO_BUILD` | Build posture (SEC-07, see "Build posture"). `true` only in the client-demo env files (`.env.staging.example`, `.env.production.example` while both environments are demos). Empty or any other value = real-operation build: no mock code, no `mockServiceWorker.js`, demo-only screens become «قريبًا» screens, and the build fails if `VITE_DEMO_MODULES` is not empty. |
| `VITE_DEMO_MODULES` | Only with `VITE_API_BASE_URL` and `VITE_DEMO_BUILD=true`. Comma-separated module keys that stay on demo data (hybrid mode, see below). Empty: every module uses the real API and the mock layer is never loaded. |
| `VITE_DEMO_LOGIN` | `true` shows the demo quick-login on the login page. It works only in `npm run dev` (`import.meta.env.DEV`); every `vite build` (staging and production) drops the component, its strings and its mock endpoint. `.env.development` sets it for local development. |
| `VITE_SENTRY_DSN`, `VITE_SENTRY_ENVIRONMENT`, `VITE_SENTRY_RELEASE` | Self-hosted GlitchTip. Every event, transaction and breadcrumb passes through `src/utils/monitoring/scrubEvent.js` (`beforeSend`, `beforeSendTransaction`, `beforeBreadcrumb`, `sendDefaultPii: false`, SEC-16): no user object, query strings and fragments dropped, token path segments, e-mails, Saudi mobiles, national ids, file names and credentials masked, form values in the extra context redacted, only safe request headers kept. |
| `MOASHIR_API_PROXY_TARGET` | Shell variable for `npm run dev` / `npm run preview` only: proxies `/api` and `/sanctum` to a local Laravel (for example `http://127.0.0.1:8010`) so the SPA and the API share one origin, as behind Nginx. |

## Hybrid mode (review and demo environments)

The review / demo deployments (operator decision Session 20) run the real API for every module that has a backend
and keep demo data, clearly marked, for the modules that do not have one yet.

| `VITE_API_BASE_URL` | `VITE_DEMO_BUILD` | `VITE_DEMO_MODULES` | Result |
|---|---|---|---|
| empty | ignored | ignored | full mock layer (local development, unit and e2e tests, the CI e2e build) |
| `/api/v1` | `true` | empty | client demo without mocks; the mock code is loaded but never started |
| `/api/v1` | `true` | `bag,ai,…` | client-demo hybrid: MSW starts with the handlers of the listed modules only and `onUnhandledRequest: 'bypass'`, so every other call reaches the real API |
| `/api/v1` | empty / `false` | empty | real operation: the mock layer and its worker are not in the build at all |
| `/api/v1` | empty / `false` | not empty | the build stops with "VITE_DEMO_MODULES must be empty in a real-operation build" |

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
- Current list (`.env.staging.example`, `.env.production.example`, both with `VITE_DEMO_BUILD=true`): `bag, ai,
  messaging, platform-settings` (no backend route yet). Since Task-37d the staff dashboards (`GET /dashboard/summary?board=`)
  and the read-only notification types catalogue (`GET /settings/notifications/types`) use the real API (`dashboards`
  and `notifications-admin` left the list); the contact inbox, the contact retention setting, the contact privacy tools,
  the legal versions management, the audit log and the staff certificate actions are real from the start. `users`,
  `committee`, `secretary` and `results` use the real API since the committee batch; `cms`, `branding` and `kpis` since
  the CMS batch (Task-34); `contact` and the public certificate levels since the home page (Task-41); the notification
  centre since Task-37a; the entity portal areas, certificates and legal pages since Task-37b; the staff settings and
  recovery pages since Task-37c. The home page never shows a demo note, and the AI assistant is hidden there in phase 1
  (route meta `hideAssistant`). Remove a key when its batch lands.

## Build posture (SEC-07)

`vite-plugins/buildPosture.js` decides at build time what ships (`src/utils/build/buildPosture.js`, unit-tested):

| Posture | When | What ships |
|---|---|---|
| `mock` | `VITE_API_BASE_URL` empty | the whole mock layer and `mockServiceWorker.js` (local development and the CI e2e build only — such a build has no backend and is never deployed) |
| `demo` | `VITE_API_BASE_URL` set and `VITE_DEMO_BUILD=true` | exactly the current client-demo behaviour: hybrid MSW for `VITE_DEMO_MODULES`, the worker, and every demo-only screen working on its mocks or on the real API as today |
| `real` | `VITE_API_BASE_URL` set and `VITE_DEMO_BUILD` empty / not `true` (the default) | `@/mocks` is replaced by an empty module (no MSW code in `dist/`), `dist/mockServiceWorker.js` is deleted after the build, demo-only routes (`meta.demoOnly`: Quick Excellence Kit, the advanced path, the Award screens, the AI knowledge base, platform general settings, abandoned-cart campaigns) render the static «قريبًا» / "Coming soon" screen (`src/router/comingSoon.js`, `views/public/ComingSoon.vue`), and the AI assistant and the SMS / e-mail campaign composers are not shown. A non-empty `VITE_DEMO_MODULES` stops the build. |

The build log prints `Moashir build posture: <posture>`. Both current environments (staging and production) are client
demos, so both example env files keep `VITE_DEMO_BUILD=true` and the deploy kit (which copies
`.env.<environment>.example` when `.env.<environment>` is absent) keeps building them exactly as before. For real
operation, provide `muasher/.env.production` (it takes precedence over the example in `deploy/deploy.sh`) with
`VITE_DEMO_BUILD=false` and an empty `VITE_DEMO_MODULES`, or change the example at go-live. A shell value overrides the
files, for example `VITE_DEMO_BUILD=false VITE_DEMO_MODULES= npm run build -- --mode production`. Check a real build with
`ls dist/mockServiceWorker.js` (absent) and `grep -rl setupWorker dist` (no match), or serve it with `vite preview` on
`:5180` and run `npm run test:e2e:posture`.

### Environment builds

```
cp .env.staging.example .env.staging.local        # add VITE_SENTRY_DSN / VITE_SENTRY_RELEASE there (never committed)
npm run build -- --mode staging
cp .env.production.example .env.production.local
npm run build -- --mode production
```

`*.local` files are ignored by version control. `VITE_DEMO_LOGIN` is `false` in both templates (and every `vite build`
drops the demo login anyway). Icons need no variable: they are inline SVGs compiled into the build (see "Icons").

### Real integration check (local backend)

The smoke suite `e2e/integration/*.spec.js` runs against a real local Laravel and is excluded from `npm run test:e2e`.
It uses its own database (default `moashir_integration`, never `moashir` or a test database) and the fictional local
staff accounts of the backend seeders. Those accounts have no password: the first sign-in of each account sets one
through `/auth/forgot-password` → the e-mailed reset link, then signs in with it and enrols the authenticator.

```
createdb moashir_integration                      # once
./scripts/integration/backend.sh setup            # migrate + seed (APP_ENV=local, fake scanner / PDF / SMS, mail to the mail log)
./scripts/integration/backend.sh serve  > /tmp/integration-serve.log 2>&1 &
./scripts/integration/backend.sh queue  > /tmp/integration-queue.log 2>&1 &
cp .env.staging.example .env.staging.local && npm run build -- --mode staging
MOASHIR_API_PROXY_TARGET=http://127.0.0.1:8010 npx vite preview --mode staging --port 5180 --strictPort &
MOASHIR_INTEGRATION_MAIL_LOGS=$(cd ../backend/storage/logs && pwd) npm run test:e2e:integration
```

E-mails are written by the backend `mail` log channel (`backend/storage/logs/mail-YYYY-MM-DD.log`): since Task-51 the
other channels redact single-use tokens, so reset and invitation links can only be read there.
`MOASHIR_INTEGRATION_MAIL_LOGS` takes files or directories (every `mail*.log` of a directory is read).
`./scripts/integration/backend.sh verify-phone <e-mail>` marks the mobile number of a fictional `example.sa` account as
verified on the integration database: the local fake SMS gateway keeps its messages in memory, so the code of
`POST /me/phone/verification` cannot be read back. The backend limits sign-in to 30 attempts per minute per address
(5 per identifier), password resets to 3 per 10 minutes per account, and public reads to 120 per minute, so run each
spec file on its own on a freshly reset database (`e2e/integration/support/pacing.js` paces the calls of one spec).
`e2e/integration/signin.spec.js` (Task-54, Task-61) covers the entity sign-in on the real contract: registration with
the unified national number and the duplicate 422, sign-in with the e-mail and the password alone, sign-in with the
unified number and a one-time code read from the mail log, the generic answer for a wrong password and an unverified
mobile, the SMS verification request, sign-in with the verified mobile (password, then a code), the identical
`POST /auth/otp/request` answer for a staff and an unknown identifier, the System Administrator's password reset and
authenticator enrolment, and the sign-in code channels screen (e-mail always on).

Run `./scripts/integration/backend.sh reset` before every repeated run (the staff authenticators are enrolled once per
database), then restart the queue worker. The suite reads one-time codes from the mail log files only; it never prints
them. It covers sign-in with MFA enrolment and challenge, criteria, entity registration and profile, self assessment
draft → submit → result, the accredited price and checkout up to the gateway boundary (`503 integration.not_configured`
without payment keys), the follower queue, integrations, award cycles, the demo markers, and a sweep of every connected
administrator and entity screen that fails on any 4xx/5xx or error state. `e2e/integration/award.spec.js` runs the award
path end to end with the seeded staff (system administrator, requests follower, assessor, filtering officer, award
secretary): a cycle is prepared through the API, a new entity applies and submits, the request is assessed and qualified,
the administrator invites a committee assessor from the users screen (the invitation is accepted through the API with a
random password), appoints it and starts the committee from the UI, the committee assessor scores and submits, the Award
Secretary approves the average, ranks and approves the region and the national result and announces them, and the entity
reads its result. Run it on a freshly reset database. `e2e/integration/staff-paths.spec.js` (Task-37c) covers the
path distinction on the real contract: assessor qualifications and the follower scope edited from the users screen, the
per-path queue (`meta.path`, `meta.path_counts`), a scoped follower, the per-path and certificate settings, the staff
entity detail with the registry details and the logo, an e-mailed invitation accepted from `/auth/invitations/:token`
and a password reset from `/auth/reset-password/:token`. It changes users and settings, so run it on a freshly reset
database too. `e2e/integration/staff-admin.spec.js` (Task-37d) covers the staff boards and the notification catalogue
read from the backend, a signed-in portal message answered from the contact inbox (reply + internal note, closed) and
read back in «رسائلي», the contact retention setting and the contact privacy export, a new privacy version drafted,
previewed and published by the Content Officer and shown on `/privacy`, an accredited request completed by the assessor
through the complete dialog (`issue_certificate`) with its certificate downloaded from the request page and verified on
`/verify/:code`, and the audit log filtered and exported. The accredited request is opened without a payment gateway by
`./scripts/integration/backend.sh open-accredited <e-mail>` (a paid order + receipt + `OpenPaidAccreditedRequest` on the
integration database only, like `verify-phone`); export `INTEGRATION_DB_DATABASE` for the spec so that it reaches the
same database.

## API contract and session

- Sanctum SPA cookies only: no token or user object is stored in the browser. Before the first
  state-changing call the client requests `GET /sanctum/csrf-cookie`; axios sends `X-XSRF-TOKEN`,
  `withCredentials` and `Accept-Language` (the active locale) on every request. A 419 refreshes the
  CSRF cookie once and retries; a second 419 or a 401 ends the session and returns to the login page.
- Sign-in offers two tabs (`src/components/auth/SignInMethodTabs.vue`, WAI-ARIA tabs, arrows follow the reading
  direction). The identifier is the official e-mail, the verified mobile number or the entity's unified national number
  (`src/utils/auth/signInIdentifier.js` mirrors the backend recognition; Arabic-Indic digits are converted) and is kept
  when switching tabs. «كلمة المرور» sends `POST /auth/login { identifier, password }`; «رمز التحقق» sends
  `POST /auth/otp/request { identifier }` (entity accounts only; the answer is always `otp_required`, with the channel
  chosen from what was typed). The flow follows the `step` field of the answer: `otp_required` → `/auth/otp` (the
  sign-in code, or the registration code of an entity that is still pending), `mfa_challenge` / `mfa_enrollment` →
  `/auth/mfa` (staff and the Super Admin only), `authenticated` → the workspace. Entity accounts have no second factor.
  The code step names only what the user typed, masked on the client (`in•••@domain`, `05•••••12`), or the registered
  e-mail in general words; it never shows a destination from the server. Wrong credentials always show one generic
  message; `account_locked` (423) and the 429 limits show the wait from `Retry-After`
  (`src/utils/auth/authFailures.js`).
- The entity sign-in code channels are a setting of the System Administrator (`/admin/settings/sign-in`, `GET/PUT
  /settings/auth { entity_sign_in_code_channels, sms_status, staff_totp_required }`): e-mail is always allowed, SMS
  can be added while the SMS gateway is configured; the Super Admin gives a reason.
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
authenticator code `246810`. Every mock e-mail uses an `example.sa` domain. The sign-in mock follows the Task-51
contract: `ENT-005001` signs in with `info@albirr-riyadh.example.sa`, its verified mobile `0500005001` or its unified
number `7100005001`, with its password or with the code `482913` (the code tab); entity accounts have no second
factor; SMS sign-in codes are off until a System Administrator allows them (mock state of the page); five failures of
any factor lock the account for 15 minutes; an account receives at most 5 codes per 10 minutes, one identifier may
request 3 codes and one browser 10 codes per 10 minutes; a code request for an unknown, staff, deactivated or locked
identifier answers the same `otp_required` and never accepts a code. `POST /me/phone/verification` answers 503 `phone.sms_unavailable` while no SMS provider row is active.

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
- Committee, secretary, results and announcement (`handlers/{committee,secretary,awardResults}.js`,
  `stores/committee/*`) mirror Task-28 §2: committee roster (appoint with SoD, remove, disable, exclude, substitute),
  blind evaluations per the `committee_entity_data_visibility` setting, automatic average once every counted row is
  submitted, discussions, secretary decisions, regional / national ranks and approvals, Super Admin unapproval and the
  manual announcement. The entity result (`GET /assessments/{id}/result`) opens after the announcement only.
- Mock scenarios (cookie `moashir_mock_scenario`, unit tests `setMockScenario`): `qualified` (cycle 31 submissions
  closed, `000004` / `000005` qualified, no committee), `committee` (committee running with assessors 27 / 29 / 30:
  `000004` under committee assessment, `000005` awaiting the secretary, `000002` in a discussion session), `results`
  (regions 2 and 4 approved, `000004` approved in region 1) and `announced` (national winners, results announced).
  Without the cookie cycle 31 stays in the submission stage and has no committee.

The payments and finance mocks (`src/mocks/handlers/{payments,finance,financeSettings,moyasarSandbox}.js`,
`src/mocks/stores/finance/*`) mirror `backend/routes/api/v1/finance.php` and `settings.php`:
- Checkout A1: `POST /payments` needs a UUID `Idempotency-Key`; the order snapshots the price from
  `/settings/finance`, the gateway mode, methods and networks come from the payment integration mock, the checkout
  object carries the intent, the snake_case `metadata` and the callback `/payments/{id}/return`. An open order of the
  same service is returned with 200. Created orders expire after `order_validity_minutes`.
- The checkout names the Moyasar form script (`checkout.script { url, integrity }`, Task-51); the SPA loads exactly
  that URL and the stylesheet of the same folder (`moyasar.css`), and sets `integrity` + `crossorigin` only when the
  backend sends a hash (`src/utils/payments/moyasarLoader.js`). There is no version pinned in the SPA. The Moyasar CDN
  sends no CORS header, so SRI cannot be enforced on a CDN script; to enforce it, self-host the pinned file on the SPA
  origin and set `MOYASAR_FORM_SCRIPT_URL` / `MOYASAR_FORM_SCRIPT_INTEGRITY` on the backend (backend README "Checkout
  script and SRI").
- No request reaches Moyasar in the normal mock run: the mock checkout names `cdn.moyasar.com/mpf/1.15.0/moyasar.js`
  (the backend default) and MSW serves every `mpf/<version>/moyasar.js` from `src/mocks/moyasar/fakeMoyasarForm.js`
  (a sandbox form with outcome choices and no card fields) and answers
  `api.moyasar.com/v1/payment_intents/{id}/fulfill`. With the cookie `moashir_moyasar_form=cdn` MSW lets the real CDN
  script through: `MOASHIR_CHECK_MOYASAR_CDN=1 npx playwright test e2e/moyasar-cdn.spec.js` (dev server, network
  needed) checks that the real form renders on the payment page (the spec is skipped by the normal run). `POST /payments/{id}/verify` reads that sandbox ledger and applies
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

The entity portal mocks (Task-37a) mirror the Task-35 / Task-46 contracts: `entity-board`
(`src/mocks/handlers/entityBoard.js`, `src/mocks/stores/entityBoard/*`) answers `GET /dashboard/action-center` and
`GET /dashboard/summary?board=entity` with the Action Center `context`, the profile completion percent and the
`latest_result` / `payments_card` widgets; `notifications` answers the list (`filter[read|archived|category|code|path]`,
`search`, `per_page`, `meta.unread_count`), `unread-count`, `read`, `archive`, `read-all` and
`GET/PUT /me/notification-preferences` (catalogue generated from the backend type definitions, mandatory types locked
with 422 `notification_preference.locked`); `contact` adds `GET /me/contact-messages` with `meta.unread_replies` and
`status_counts`; `cms` serves `GET /content/assessment-paths/{accredited|advanced}`; `accredited` adds
`active.progress` and `entities` adds `profile_completion`.

## Brand identity

- Source of truth: the official identity guide (`../agents/design/moashir-identity.pdf`); review and token mapping in
  `../agents/tasks/reports/Task-38-identity-review.md`.
- Colours are tokens only (`src/styles/tokens/_colors.scss`): `#0a2325`, `#98d8d3`, `#d9f7f7`, gold / bronze / silver
  and the derived AA text tokens. Never use `#98d8d3` for small text on light surfaces.
- Logos: official SVGs in `src/assets/logos/` (on light, on dark, mono dark / grey / teal, star, arrow), extracted from
  the guide's vector outlines. Patterns: `src/assets/patterns/` through `components/ui/BrandPattern.vue`.
- Favicon / app icons / social cards in `public/` use the star mark on `#0a2325`.
- Fonts: Montserrat (Latin) + Tajawal / IBM Plex Sans Arabic (Arabic) until a web licence for the guide's Arabic font is
  provided — see `src/assets/fonts/README.md`.

## Icons (TTS icon set, inline SVG)

Every icon is a glyph of the TTS icon set ("TTS Panel Design System" Figma library, `Icon/<name>`, Iconly-based),
drawn by one component, `src/components/ui/AppIcon.vue`. There is no icon font, kit, CDN or sprite request: the SVG
path data is compiled into the JavaScript bundle and rendered inline, so icons render offline, under the strict CSP and
in every build.

| Piece | Role |
|---|---|
| `src/assets/icons/tts/<name>.svg` | One glyph per file: `viewBox="0 0 24 24"`, `fill="currentColor"`, paths only. |
| `src/assets/icons/tts/pending.json` | Icons the platform needs that TTS has not published yet: `glyph` (name → stop-gap TTS glyph), `brand` (social logos → the generic `link` glyph until the official brand marks are supplied) and `withheld` (`flask`, `language`, `pause`, `phone`: their stop-gaps misled, so nothing is drawn — every use has a visible text label — until TTS publishes the glyph). |
| `src/assets/icons/interim/<name>.svg` | Interim glyphs derived from a TTS glyph until TTS publishes the real one. Today only `bell`: the TTS `bell-dot` outline closed by mirroring its left half, without the dot. The bells always draw `bell` and overlay the unread badge / dot only when something is unread. A published `tts/bell.svg` wins automatically; then delete the interim file (a unit test fails while both exist). |
| `src/utils/icons/iconRegistry.js` | The single registry: every SVG of the folder (`import.meta.glob`, raw, eager), the pending stop-gaps and the aliases. `resolveIcon(name)` returns the path data, the RTL mirroring and the flip / spin flags. |
| `src/constants/icons.js` | Size tokens, the RTL mirroring list, the aliases (`undo` = flipped `refresh`, `spinner` = spinning `refresh-dots`) and the stored-value pattern. |
| `src/utils/icons/storedIcon.js` + `legacyIconNames.js` | CMS and KPI icons are still stored by the API in the legacy class format (`fa-solid fa-chart-line`, backend rule `FontAwesomeIcon`). The registry reads such values as platform names; the CMS / KPI forms write `fa-solid fa-<name>` until the backend stores plain names. |

Usage:

```vue
<AppIcon name="search" />
<AppIcon name="gear" size="lg" />
<AppIcon name="arrow-right" :mirror="false" />
<button type="button" class="btn btn--icon"><AppIcon name="x" :label="t('actions.close')" /></button>
```

- `name`: a TTS name, a pending name (drawn with its stop-gap) or an alias. Props that used to carry icon class strings
  (`BaseButton` `icon` / `icon-end`, menu actions, stat cards, notices …) now carry these names.
- `size`: `sm` 16 px, `md` 20 px, `lg` 24 px (`--icon-size-*`). Without a size the icon is `1em`, sized by its context.
- `label`: for an icon that is the only content of a control → `role="img"` + `aria-label`. Without it the icon is
  decorative (`aria-hidden="true"`, `focusable="false"`); the visible text of the control carries the meaning.
- RTL: `arrow-left`, `arrow-right`, `chevron-left`, `chevron-right`, `chevron-sm-left`, `chevron-sm-right`, `send`,
  `login`, `logout` and `reply` are mirrored in Arabic (CSS `scale`, so it composes with component rotations). A use
  that means a physical direction opts out with `:mirror="false"`. Everything else is never mirrored.
- Colour comes from `currentColor`, so icons follow the text colour tokens of their context.

Adding or replacing an icon when TTS publishes it (phase A2) is a data-only change: drop `<name>.svg` into
`src/assets/icons/tts/` and delete its entry from `pending.json` (or the interim file). `hasIcon(name)` tells a
component whether a glyph is drawn, so a decorative tile or a fixed icon column can stay aligned without one. The unit tests fail while a pending entry still has its
own SVG or points to a missing stop-gap. Licence: the glyphs come from the "TTS Pro icon font (Iconly)"; their use in
the client deliverable relies on TTS's Iconly licence — operator to confirm in the handover.

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
