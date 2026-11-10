# Moashir deployment runbook

Two environments of the same code run on one Ubuntu 24.04 server, each isolated from the other:

| Environment | Domain | `APP_ENV` | OS user | Database | Redis |
|---|---|---|---|---|---|
| staging | `staging.moashir.ttshub.live` | `staging` | `moashir-staging` | `moashir_staging` | private instance, socket `/run/moashir-redis-staging/redis.sock` |
| production | `moashir.ttshub.live` | `production` | `moashir-production` | `moashir_production` | private instance, socket `/run/moashir-redis-production/redis.sock` |

## Environment postures (demo or real operation)

Every environment has one posture, recorded as `MOASHIR_DEMO` in its `.env`:

| Posture | `MOASHIR_DEMO` | What the kit allows |
|---|---|---|
| Real operation (the default of both templates) | `false` | `APP_DEMO_DATA=false`; `MAIL_MAILER=settings` (mail through the SMTP settings screen, never to a log file); `SECURITY_ALERT_MAIL` and a real `BACKUP_NOTIFICATION_MAIL` are required. `demo-install` is refused. A release is not activated while any of these is wrong. |
| Demo (explicit opt-in) | `true` | Fictional data only. `demo-install`, demo password rotation and the `log` mailer are allowed. |

- Opt in: `bash /opt/moashir/deploy/provision.sh posture demo <staging|production|all>`.
- Real operation: `bash /opt/moashir/deploy/provision.sh posture real <env>`. This refuses a database that holds
  demo records. Real operation always starts on a fresh database, never on the demo database. The command sets
  `MOASHIR_DEMO=false`, `APP_DEMO_DATA=false` and `MAIL_MAILER=settings`, shreds the demo accounts file, and lists
  what is still missing in the `.env`.
- The backend reads the same `MOASHIR_DEMO` flag for its own mailer guard.
- The SPA has its own build posture, `VITE_DEMO_BUILD`, read from `muasher/.env.<env>` (the kit copies
  `muasher/.env.<env>.example` when that file is absent). No API build ships mock code in either posture: modules
  with no backend yet (the Kit, the AI knowledge base and assistant, platform general settings, the campaign
  composers) show "Coming soon" or stay hidden. `true` keeps the backend-served screens that are still marked
  demo-only (advanced path, Award, abandoned carts) open; empty or `false` shows "Coming soon" there too. Both
  examples are set to `true` today. When an environment moves to real operation, set `VITE_DEMO_BUILD=false` in that
  environment's example (or provide `muasher/.env.<env>`) in the same change that switches the server posture.
  `VITE_DEMO_MODULES` is no longer read; a leftover value is ignored.
- `moashir:demo:purge` removes the registered demo records (dry run by default, `--force` to delete). It refuses
  unless `MOASHIR_DEMO=true`, and with `--force` it also needs the database owner role used for migrations (the
  app role cannot delete the append-only tables). Run it only on the operator's explicit instruction.

The current server hosts the CLIENT DEMO: both environments run in the demo posture with fictional data.

Demo-only relaxations to undo before real operation: the session timeouts in both demo `.env` files were raised (idle 60 / 120 / 120 minutes, `SESSION_LIFETIME=120`); real operation uses the template values (15 / 30 / 60, lifetime 60).

## Temporary hosting exception

The current server is outside the Kingdom of Saudi Arabia and is shared with other sites. It is used for review
and the client demo only, with fictional data, no real personal data and no live provider keys. Real operation
starts on a new in-Kingdom host provisioned with this kit (section "New host"). This setup is then removed
(section "Remove everything").

## What runs where

| Item | Location |
|---|---|
| System users | `moashir-staging`, `moashir-production` (no login shell, homes `/var/lib/moashir-<env>`, member of `clamav`) |
| Releases | `/var/www/moashir/<env>/releases/<UTC timestamp>/{backend,frontend,REVISION}`, `current` symlink, 3 kept |
| Shared files | `/var/www/moashir/<env>/shared/.env` (600, owner `moashir-<env>`), `shared/storage`, `shared/tmp` |
| Database owner credentials | `/etc/moashir/<env>/database-owner.env` (root only, used for migrations) |
| PHP-FPM | pool `moashir-<env>` running as `moashir-<env>`, socket `/run/php/php8.4-fpm-moashir-<env>.sock` (group `www-data`, 660) |
| Redis | `moashir-redis@<env>.service`, config `/etc/moashir-redis/<env>.conf`, data `/var/lib/moashir-redis-<env>`, unix socket only |
| Queue | Supervisor program `moashir-<env>-horizon` (user `moashir-<env>`) |
| Scheduler | `/etc/cron.d/moashir-<env>` (every minute, user `moashir-<env>`) |
| Backups | timer `moashir-backup@<env>.timer` (daily 23:30 UTC); key `/etc/moashir/<env>/backup.key` (root, 400); target settings `/etc/moashir/<env>/backup-target.env` (root, 600); local copy of the latest set `/var/backups/moashir/<env>` (root, 700) |
| Logs | Nginx `/var/log/moashir/nginx/<env>.{access,error}.log`; operational logs `/var/log/moashir/<env>/` (14 days); Laravel `shared/storage/logs` (14 days); root-only archive `/var/log/moashir/archive/` (400 days) |
| Nginx | `/etc/nginx/sites-available/moashir-<env>.conf`, snippets `/etc/nginx/snippets/moashir-*`, `/etc/nginx/conf.d/moashir-http.conf` |
| Certificates | Let's Encrypt `moashir-staging`, `moashir-production` (webroot `/var/www/moashir/acme`) |
| ClamAV | `clamav-daemon` + `clamav-freshclam`, socket `/var/run/clamav/clamd.ctl` (group `clamav`, mode 660) |
| PDF renderer | Chrome for Testing `chrome-headless-shell` in `/opt/moashir/chrome-headless-shell/<version>`, `current` symlink, AppArmor profile `/etc/apparmor.d/moashir-chrome-headless-shell` |
| Deployment kit | `/opt/moashir/deploy` (root owned, `KIT_DIGEST` identifies the version); Composer in `/opt/moashir/bin` when the server has none |
| CI deploy user (optional) | `moashir-deploy`, uploads into `/var/lib/moashir-deploy/incoming/<env>`, one sudo rule `/etc/sudoers.d/moashir-deploy` |
| Demo accounts (demo posture) | `/root/moashir-<env>-demo-accounts.txt` (root only, mode 600) |

Shared services are never restarted: Nginx and PHP-FPM are only reloaded after `nginx -t` / `php-fpm8.4 -t`.
Supervisor only registers the Moashir programs. PostgreSQL and the server-wide Redis receive no configuration
change. The two Moashir Redis instances are separate systemd units. Other sites on the server are not touched.

## Before you start (operator workstation)

1. Tools: `ssh`, `tar`, `curl`, Node 24 + npm (SPA build). Root SSH access to the server with a key.
2. Export the server address for this shell only; it is never written into the repository:

   ```
   export DEPLOY_HOST=<server address>
   ```

   Optional: `DEPLOY_SSH_PORT` (default `22`), `DEPLOY_SSH_IDENTITY_FILE`, `DEPLOY_SSH_KNOWN_HOSTS_FILE`
   (strict host key checking), `DEPLOY_COMMIT` (the full commit id of the tree you deploy; it is recorded in
   `APP_RELEASE`).
   `DEPLOY_SSH_USER` is `root` for the operator (default) or `moashir-deploy` for CI.
3. Both DNS records (`staging.moashir.ttshub.live`, `moashir.ttshub.live`) must point to the server.
4. Before every push run `bash deploy/secrets-scan.sh` from the repository root (see "Secrets scan").

## New host: exact order of the first installation

Run the `provision.sh` phases as root on the server (`ssh root@$DEPLOY_HOST bash /opt/moashir/deploy/provision.sh <phase>`).

0. **Prerequisites** (installed by the operator on the fresh Ubuntu 24.04 host, full-disk encryption on):
   `nginx`, `php8.4-fpm` and `php8.4-cli` from a trusted PHP 8.4 source, `supervisor`, `certbot`, `redis-server`
   (7.x), `curl`, `sudo`, `openssl`. On this dedicated host run `systemctl disable --now redis-server`: Moashir
   uses only its private per-environment instances, and the package's shared instance must not run. Enable
   unattended security upgrades.
1. **Upload the kit**: `deploy/deploy.sh staging sync-kit`
2. **PostgreSQL 17**: `provision.sh postgresql`. This adds the PostgreSQL apt repository (signing key fingerprint
   `B97B0AFCAA1A47F044F244A07FCC7D46ACCC4CF8` verified) and installs `postgresql-17`. It changes nothing when a
   cluster already exists.
3. **System**: `provision.sh system`. This covers:
   - packages, 2 GiB swap, ClamAV and its signatures, Composer;
   - per environment: the system user, directories, the `.env` (real-operation posture, new `APP_KEY`), the
     database and its roles, the private Redis, the PHP-FPM pool, Horizon, cron, log rotation and archive, the
     backup key, the target template and the timers;
   - the PDF renderer.

   Passwords and keys are generated on the server (`openssl rand`) and written only to root-only or
   environment-only files. Nothing is printed.
4. **Escrow the backup keys** (section "Backups", step 1). Do this now, before any data exists.
5. **Fill in the `.env` of each environment**: `SECURITY_ALERT_MAIL`, `BACKUP_NOTIFICATION_MAIL`, and the SMTP
   settings in the e-mail settings screen after the first deployment. A release is not activated in the
   real-operation posture while the first two are empty. For a test staging, use `provision.sh posture demo staging`.
6. **Nginx bootstrap** (HTTP only, ACME challenge): `provision.sh nginx-bootstrap`
7. **Certificates for the Moashir domains only**: `provision.sh certificates`. With several certbot accounts add
   `MOASHIR_CERTBOT_ACCOUNT=<account id>`; to register with an address use `MOASHIR_CERTBOT_EMAIL=<address>`.
8. **Final Nginx** (HTTPS, SPA, `/api`, `/sanctum`, Apple Pay path, headers, upload limits): `provision.sh nginx-final`
9. **Default TLS server (dedicated host only)**: `provision.sh nginx-default-tls`. It refuses when another site is
   enabled; on a fresh Ubuntu, remove `/etc/nginx/sites-enabled/default` first.
10. **Deploy staging**: `deploy/deploy.sh staging`. This runs the whole release:
    - builds the SPA, uploads the release and runs `composer install --no-dev --optimize-autoloader`;
    - runs `migrate --force` with the owner role, applies the database grants and the reference data;
    - builds the 4 caches, switches `current` atomically and restarts Horizon;
    - runs the smoke checks.

    `db:seed` is never run.
11. **Backups**: configure the off-server target, then `backup-run`, `backup-check`, `restore-test` (section "Backups").
12. **Deploy production**: `deploy/deploy.sh production`
13. **Super Admin** (the operator, interactively, once per environment): `deploy/deploy.sh <env> super-admin`.
    E-mail must work first (SMTP settings), because the first password link is sent by e-mail.
14. **Optional CI-gated deployment**: section "CI-gated deployment".

Later deployments: `deploy/deploy.sh <env>` from the workstation, or the `Deploy` workflow.

## Applying Task-52 to an existing server

This section is for the current demo server, which was installed with the previous kit (one shared `moashir` user,
shared Redis, demo data in both environments). Run the steps in this order, with the operator's permission and
outside demo hours. The demo keeps working after every step. A step that changes an environment puts only that
environment into maintenance mode for one to two minutes.

**0. Read-only checks and a safety copy** (as root on the server):

```
bash /opt/moashir/deploy/provision.sh status
redis-server --version          # must be 7.0 or newer
dpkg-query -W redis-server      # the package that provides /usr/bin/redis-server
df -h /var /var/backups
for env in staging production; do
  sudo -u postgres pg_dump -Fc moashir_${env} > /root/moashir_${env}-before-task52.dump
  chmod 600 /root/moashir_${env}-before-task52.dump
done
```

**1. Upload the new kit** (workstation): `deploy/deploy.sh staging sync-kit`. Only `/opt/moashir/deploy` changes;
the previous kit stays in `/opt/moashir/deploy.previous`.

**2. Keep the demo posture**: `provision.sh posture demo all`. This writes `MOASHIR_DEMO=true` into both `.env`
files. Without this step the next activation refuses the `log` mailer and demo data, and the old release keeps
running.

**3. One OS user per environment**, staging first:

```
bash /opt/moashir/deploy/provision.sh migrate-user staging
bash /opt/moashir/deploy/provision.sh migrate-user production
```

Each run:
- creates `moashir-<env>`;
- puts the environment into maintenance mode, stops its Horizon and scheduler, and waits for its jobs;
- gives every file of `/var/www/moashir/<env>` and `/var/log/moashir/<env>` to the new user (the `www-data` group
  on the SPA files is kept);
- rewrites the PHP-FPM pool, the Horizon program, the cron entry and the log rotation, and reloads PHP-FPM;
- restarts Horizon and leaves maintenance mode;
- checks that the `.env`, pool and Horizon all use the new user.

The command is idempotent; if it stops halfway, run it again. Check: open the site, sign in with a demo account.

**4. One private Redis per environment**, staging first:

```
bash /opt/moashir/deploy/provision.sh redis-cutover staging
bash /opt/moashir/deploy/provision.sh redis-cutover production
```

Each run:
- starts `moashir-redis@<env>` (unix socket only, 660, owner `moashir-<env>`, no TCP, no network namespace);
- saves the `.env` to `/etc/moashir/<env>/env.before-redis-cutover` (root only) and enters maintenance mode;
- stops Horizon and copies every `moashir_<env>_*` key (queues including delayed jobs, cache, rate limits, Horizon
  data) from the shared Redis into the private one, keeping expiry times;
- points the `.env` at the socket (`REDIS_HOST=/run/moashir-redis-<env>/redis.sock`, `REDIS_PORT=0`,
  `REDIS_DB=0`, `REDIS_CACHE_DB=1`, empty password);
- rebuilds the configuration cache, reloads PHP-FPM, starts Horizon, leaves maintenance mode and waits until
  Horizon reports running.

Any failure restores the saved `.env` automatically. The keys stay in the shared Redis as a rollback point
(step 9).

**5. System phase with the new kit**: `provision.sh system`. This is now idempotent for the migrated environments
and adds:
- missing packages (`gnupg`);
- the ClamAV limits for the 100 MB upload ceiling (clamd restarts once; uploads fail closed for those seconds);
- the PHP upload limits;
- the backup keys, target templates and daily timers;
- the application log archive timers and per-environment log rotation.

It stops with a clear message if an environment still uses the shared user or the shared Redis. Then escrow the
two backup keys (section "Backups", step 1).

**6. Final Nginx with the new templates**: `provision.sh nginx-final`. This adds:
- the per-route upload limits and the JSON 413 answer for `/api`;
- the explicit TLS policy, and the access log that masks reset / invitation tokens;
- the new log location `/var/log/moashir/nginx/` (400 days, root-only archive).

The previous Moashir logs in `/var/log/nginx/moashir-*` move into `/var/log/moashir/archive/nginx/legacy`.
Only `nginx -t` + reload; other sites are unchanged.

**7. Deploy both environments** (workstation): `deploy/deploy.sh staging`, then `deploy/deploy.sh production`. The
smoke checks must show no `FAIL`; `warn` lines about the off-server backup target remain until step 8.

**8. Backups**: create the off-server target, fill in `/etc/moashir/<env>/backup-target.env`, then for each
environment `deploy/deploy.sh <env> backup-run`, `backup-check`, `restore-test`. Until the target exists,
`backup-run` and `restore-test` still work on the local encrypted copy and prove the mechanism.

**9. Clean-up after one or two days of normal operation**:

```
bash /opt/moashir/deploy/provision.sh redis-release-shared staging
bash /opt/moashir/deploy/provision.sh redis-release-shared production
bash /opt/moashir/deploy/provision.sh remove-legacy-user
shred -u /root/moashir_staging-before-task52.dump /root/moashir_production-before-task52.dump
```

`redis-release-shared` deletes only `moashir_<env>_*` keys from the shared Redis databases and checks that none
remains; other applications' keys are untouched. `remove-legacy-user` refuses while any process or file still
belongs to `moashir`.

**10. Optional**: CI-gated deployment (section "CI-gated deployment"). PostgreSQL 17 (section "PostgreSQL 17").

**Reverting a step**:
- Redis cutover of an environment:

  ```
  install -m 600 -o moashir-<env> -g moashir-<env> /etc/moashir/<env>/env.before-redis-cutover /var/www/moashir/<env>/shared/.env
  ```

  then `sudo -u moashir-<env> php8.4 /var/www/moashir/<env>/current/backend/artisan config:cache` and
  `supervisorctl restart moashir-<env>-horizon`. Jobs queued after the cutover stay in the private instance.
- Shared user: `chown -R --from=moashir-<env> moashir /var/www/moashir/<env> /var/log/moashir/<env>`,
  `chgrp -R --from=:moashir-<env> moashir` on the same paths, then `bash /opt/moashir/deploy.previous/provision.sh system <env>`.
- Kit: `mv /opt/moashir/deploy /opt/moashir/deploy.failed && mv /opt/moashir/deploy.previous /opt/moashir/deploy`.

## Manual smoke checks (both environments)

- Open the site in Arabic and English; the language switch works; no browser console errors.
- Sign in with a staff account: the TOTP enrolment is requested (MFA is mandatory for staff).
- Sign in with an entity account; its requests and payments are visible.
- Payment checkout in TEST mode only after the client entered TEST keys in the payment settings screen. Open the
  browser console on the checkout page: any Content-Security-Policy violation names an origin that must be added to
  `/etc/nginx/snippets/moashir-spa-headers.conf` (source: `deploy/nginx/moashir-spa-headers.conf`) followed by
  `nginx -t && systemctl reload nginx`.
- Upload an evidence file (scanned by ClamAV; the EICAR test string must be refused). Upload a 3 MB CMS image
  and a 1.5 MB entity logo: they must reach the application (no "413 Request Entity Too Large" page).

The automatic smoke checks (`deploy/deploy.sh <env> smoke`) cover:
- HTTP answers and security headers;
- every upload route limit, plus the JSON 413 on the other routes;
- the `.env` mode and owner, `APP_DEBUG`, and the posture;
- that Horizon and the PHP-FPM workers run as the environment user;
- the private Redis (socket path, mode, no TCP);
- ClamAV, and the PDF renderer;
- the backup key and timer.

Missing off-server target and a missing recent backup are `warn` lines.

## Demo data (demo posture only)

- Command: `php artisan moashir:demo:install --accounts-file=<absolute path outside the application>`, wrapped by
  `deploy/deploy.sh <env> demo-install`.
- Refused by the kit unless the environment is in the demo posture (`MOASHIR_DEMO=true`). The application also
  needs `APP_DEMO_DATA=true`. It refuses (critical log line `platform.demo_data_refused`) when an entity exists
  that the demo command did not create, or when the demo is already installed.
- It creates:
  - one staff account per staff role (MFA required, TOTP enrolment at first sign-in);
  - three fictional entities with their owner accounts;
  - demo terms and privacy texts when none are published, and the accredited price when none is set;
  - one draft award cycle and two TEST-mode payments.
- Passwords are random and written only to `/root/moashir-<env>-demo-accounts.txt` (mode 600). Read the file on
  the server; never paste it into chat, tickets or the repository.
- Outgoing e-mail in the demo posture may use the `log` mailer (`shared/storage/logs/mail-<date>.log`, mode 600,
  kept 7 days). It contains sign-in codes and links: never use it with real people. Switch to
  `MAIL_MAILER=settings` once SMTP is configured AND its connection test in the control panel passed (the
  integration row shows `last_test_ok = true`); switching earlier makes every e-mail, including sign-in codes, fail.
- Outbound SMTP ports depend on the hosting provider. On the current demo server (DigitalOcean) 465, 587 and 2525 are
  blocked and 2465 / 2587 are open (tested 2026-11-04 with `timeout 6 bash -c "</dev/tcp/<smtp host>/<port>"`); use
  2465 with TLS (or 2587 with STARTTLS) for providers that offer them (e.g. Resend). Re-test the ports on any new host,
  including the in-Kingdom host, before switching the mailer.
- `deploy/deploy.sh <env> demo-rotate-passwords` issues new random passwords for every demo account, ends their
  sessions and replaces the accounts file.

## Backups and restore

Two layers exist:

| Layer | What | Where | Key | Purpose |
|---|---|---|---|---|
| Disaster recovery (this kit) | database (plain SQL, row counts recorded), `shared/storage/app` (evidence, logos, images), the environment's `.env` (holds `APP_KEY`) | off-server target (S3-compatible object storage or SFTP, inside the Kingdom); latest set also in `/var/backups/moashir/<env>` | `/etc/moashir/<env>/backup.key`: root only, never in the `.env`, never readable by the application user | survives the loss of the server |
| Application archives (spatie) | database + private files | on the server (`storage/app/private/<APP_NAME>`), 30 days | `BACKUP_ARCHIVE_PASSWORD` in the `.env` | quick restore of recent data; not a disaster-recovery copy |

Encryption: GnuPG symmetric AES-256 in OCB (authenticated) mode with a 384-bit random key. A wrong key or a
changed byte makes the decryption fail. Parts larger than `BACKUP_PART_MEGABYTES` (default 1024) are split. Each
set carries `checksums.sha256` (uploaded last, so a set without it is incomplete) and an encrypted manifest (row
count per table, file count, PostgreSQL version).

1. **Escrow the key (mandatory, once per environment)**. Without the key no backup can ever be restored:

   ```
   cat /etc/moashir/<env>/backup.key
   ```

   Store the value in the client's offline key vault (two custodians), never in the repository, chat or the
   target. `deploy/deploy.sh <env> backup-check` prints a fingerprint (the first 16 hex characters of its SHA-256)
   to compare with the escrowed copy. Rotating the key: keep the old key in the vault for 30 days, replace the file
   (root, mode 400), run `backup-run` and `restore-test`.
2. **Choose and configure the off-server target** (the operator decides; it must be inside the Kingdom). Edit
   `/etc/moashir/<env>/backup-target.env` (root, 600):

   | Name | Meaning |
   |---|---|
   | `BACKUP_TARGET_DRIVER` | `s3` or `sftp` (empty = no off-server target) |
   | `BACKUP_RETENTION_DAYS` | days kept on the target (default 30; the newest set is never deleted) |
   | `BACKUP_TARGET_PRUNE` | `true`: the kit deletes old sets; `false`: the target's own lifecycle rule does it |
   | `BACKUP_PART_MEGABYTES` | maximum part size (default 1024) |
   | `BACKUP_S3_ENDPOINT`, `BACKUP_S3_REGION`, `BACKUP_S3_BUCKET`, `BACKUP_S3_PREFIX` | S3-compatible endpoint (`https://host`), region, bucket, key prefix (path-style addressing, SigV4) |
   | `BACKUP_S3_ACCESS_KEY_ID`, `BACKUP_S3_SECRET_ACCESS_KEY` | credentials of a key limited to that bucket / prefix |
   | `BACKUP_SFTP_HOST`, `BACKUP_SFTP_PORT`, `BACKUP_SFTP_USER`, `BACKUP_SFTP_PATH` | SFTP host, port, user, absolute base path |
   | `BACKUP_SFTP_IDENTITY_FILE`, `BACKUP_SFTP_KNOWN_HOSTS_FILE` | root-only private key (600) and the verified host key file |

   Recommended (ransomware resilience): enable versioning / object lock with a 30-day lifecycle on the bucket, give
   the server a key that can write but not delete, and set `BACKUP_TARGET_PRUNE=false`. Use a different prefix or
   path per environment, or a different bucket.
3. **Run and check**:
   - `deploy/deploy.sh <env> backup-run`: an immediate backup, the same as the daily timer.
   - `deploy/deploy.sh <env> backup-check`: key, timer, last backup, sets on the target, last restore test.
   - `journalctl -u moashir-backup@<env>` shows the timer runs. A failed run leaves the unit in `failed` state
     (`systemctl --failed`).
4. **Restore test (at least quarterly, and after every target or key change)**: `deploy/deploy.sh <env> restore-test`.
   - It downloads the newest complete set from the target (or uses the local copy, with a warning, when no target
     is configured).
   - It verifies the checksums, decrypts the parts and restores the database into a throw-away database
     `moashir_restore_test_<env>_<timestamp>`.
   - It compares every table's row count with the manifest, reads the whole file archive and compares the file
     count, and checks that the `.env` copy holds `APP_KEY`.
   - It drops the throw-away database, records `PASS` / `FAIL` and exits non-zero on `FAIL`.

   Keep its output as the evidence of the restore drill.
5. **Full recovery on a new host** (disaster):
   1. provision the new host up to step 3 of "New host";
   2. download the set and decrypt each part with the escrowed key. Join any split parts first with
      `cat <name>.part-* > <name>`. Then decrypt:

      ```
      gpg --batch --pinentry-mode loopback --passphrase-file <key file> --decrypt <part>.gpg > <part>
      ```
   3. restore the database into the empty database as the `postgres` user:

      ```
      gunzip -c database.sql.gz | sudo -u postgres psql -v ON_ERROR_STOP=1 -d moashir_<env>
      ```
   4. extract `files.tar.gz` into `shared/storage/app` and give it to `moashir-<env>`;
   5. copy `APP_KEY` and `APP_PREVIOUS_KEYS` from the restored `.env` into the new `.env`;
   6. deploy, smoke-check, then `restore-test` on the new host.

## Logs, retention and monitoring

| Log | Where | Kept | Access |
|---|---|---|---|
| Nginx access / error (Moashir only) | `/var/log/moashir/nginx/<env>.*.log`, rotated daily into `/var/log/moashir/archive/nginx/` (compressed) | 400 days | live file: root and `www-data`; archive: root only |
| Laravel application log (sign-in failures, lockouts, webhook rejections, critical events) | `shared/storage/logs/laravel-<date>.log`; closed days are copied daily by `moashir-log-archive@<env>.timer` into `/var/log/moashir/archive/<env>/application/` (gzip) | 14 days live, 400 days archived | archive: root only |
| PHP-FPM, Horizon, scheduler | `/var/log/moashir/<env>/` | 14 days | environment user |
| Audit trail and authentication events | database tables (append-only) | per the data retention policy; in every backup | System Administrator screen |

- The access log never records query strings. The token segment of `/auth/reset-password/<token>`,
  `/auth/invitations/<token>` and `/api/v1/auth/invitations/<token>/accept` is written as `[redacted]`. The Nginx
  error log can still contain a request line when an error occurs on such a path; it is archived root-only.
- Before this kit version, Moashir's Nginx logs were in `/var/log/nginx/moashir-*`; `nginx-final` moves them into
  `/var/log/moashir/archive/nginx/legacy`.
- Central monitoring: copy `/var/log/moashir/archive/` to the client's in-Kingdom log store / SIEM when it exists.
- **GlitchTip** (self-hosted, inside the Kingdom; the host is chosen by the operator):
  1. backend: set `SENTRY_LARAVEL_DSN` in each `.env`. `SENTRY_ENVIRONMENT` is preset per environment, and
     `SENTRY_RELEASE` is written by every activation with the same label as `APP_RELEASE`;
  2. frontend: set `VITE_SENTRY_DSN` at build time. On the workstation, export it before `deploy.sh`. In CI, set the
     environment variable `VITE_SENTRY_DSN` of the GitHub Environment. `VITE_SENTRY_RELEASE` is set automatically to
     the same release label;
  3. add the GlitchTip origin to `connect-src` in `deploy/nginx/moashir-spa-headers.conf`, then run `nginx-final`;
  4. keep `SENTRY_SEND_DEFAULT_PII` off, and configure alert rules and retention in GlitchTip. Route alerts to
     `SECURITY_ALERT_MAIL`;
  5. deploy, then trigger a test error on staging.

## Upload limits

Nginx accepts a request body only up to the application's own limit plus a small multipart margin. Larger bodies
get a JSON `413` with code `request.too_large` (Arabic by default, English for `Accept-Language: en`). They never
get Nginx's HTML page.

| Route | Application rule | Nginx limit |
|---|---|---|
| `POST /api/v1/assessments/{id}/evidence`, `POST /api/v1/finance/payments/{id}/refunds`, `POST /api/v1/payments` (bank transfer with its receipt) | setting `upload_max_file_mb` / `general_upload_max_file_mb` (default 20 MB, the validation allows up to 100 MB) | `101m` |
| `POST /api/v1/admin/content/images`, `POST /api/v1/admin/kpis/images` | `moashir.cms.image_max_kb` = 5120 KB | `5376k` |
| `POST /api/v1/entities/me/logo` | `moashir.entities.logo.max_kb` = 2048 KB | `2304k` |
| `POST /api/v1/settings/branding/logo` | `moashir.branding.logo_max_kb` = 1024 KB | `1280k` |
| every other `/api` and `/sanctum` route (JSON) | n/a (Apple Pay association file: 64 KB) | `2m` |
| static SPA paths | n/a | `64k` |

PHP accepts `upload_max_filesize = 100M` and `post_max_size = 101M`. ClamAV scans up to 101 MB per file. When an
application limit changes, update `deploy/lib/provision-nginx.sh` (and `EVIDENCE_UPLOAD_CEILING_MB` in
`deploy/provision.sh`), then run `nginx-final` (and `system` for PHP / ClamAV). If the evidence setting is raised
above about 50 MB, raise `FILES_CLAMAV_TIMEOUT_SECONDS` too.

## TLS

Every Moashir server block sets its own policy:
- TLS 1.2 and 1.3 only;
- TLS 1.2 suites ECDHE with AES-GCM only;
- TLS 1.3 suites `TLS_AES_256_GCM_SHA384` and `TLS_AES_128_GCM_SHA256`;
- curves P-256 / P-384, no session tickets;
- HSTS for one year with subdomains (SPA snippet and API).

Nginx negotiates the protocol version and the TLS 1.3 suites before the server name is known, so on a shared IP
they follow the DEFAULT server of that IP. On the current shared server that default belongs to another site and
the kit does not change it. Ask its owner to apply the same `ssl_protocols` / `ssl_ciphers` /
`ssl_conf_command Ciphersuites` at the `http {}` level or in the default server. Then verify from a workstation:

```
nmap --script ssl-enum-ciphers -p 443 moashir.ttshub.live
openssl s_client -connect moashir.ttshub.live:443 -servername moashir.ttshub.live -tls1_1   # must fail
```

On a dedicated host, `provision.sh nginx-default-tls` installs a default `:443` server with `ssl_reject_handshake on`
and the same policy, so the whole IP follows it. Record the scan output as evidence.

## Redis per environment

- Each environment has its own `redis-server` (systemd unit `moashir-redis@<env>`, Redis 7). It runs as the
  environment user and listens only on the unix socket `/run/moashir-redis-<env>/redis.sock` (mode 660, owner and
  group `moashir-<env>`). It has `port 0`, a private network namespace and an `AF_UNIX`-only address-family filter.
- Only that environment's PHP-FPM pool, Horizon and scheduler (and root) can connect. Another site, another local
  account or the other environment cannot reach it. Queue payloads therefore cannot be injected across
  environments.
- Persistence: AOF (`everysec`) plus RDB snapshots in `/var/lib/moashir-redis-<env>`. Memory: 256 MB
  (production) / 128 MB (staging) with `noeviction`, so queued jobs and rate-limit counters are never evicted
  silently.
- `CONFIG` cannot change protected settings (`dir`, `dbfilename`), and the `DEBUG` and `MODULE` commands are
  disabled. No password is set: the socket permissions are the access control.

## PostgreSQL 17

- New host: `provision.sh postgresql` installs PostgreSQL 17 (step 2 of "New host"). CI tests against PostgreSQL 17.
- Existing demo server (PostgreSQL 16, shared with other sites): not forced. `system` prints a warning. Upgrade
  path, when the operator decides:
  1. `provision.sh postgresql-repository` (adds only the verified PostgreSQL apt repository), then
     `apt-get update && apt-get install postgresql-17`. Ubuntu then creates a separate cluster `17/main` on port
     5433 without touching the existing one; check with `pg_lsclusters`;
  2. `provision.sh posture demo all` is already set. Put both environments into maintenance mode
     (`artisan down`), stop their Horizon programs and run `provision.sh status` to check;
  3. for each environment, dump from 16 and restore into 17 (roles first):

     ```
     sudo -u postgres pg_dumpall -p 5432 --roles-only | grep -E 'moashir_(staging|production)_' | sudo -u postgres psql -p 5433
     sudo -u postgres pg_dump -p 5432 -Fc moashir_<env> > /root/moashir_<env>.pg16.dump
     sudo -u postgres createdb -p 5433 -T template0 -E UTF8 -O moashir_<env>_owner moashir_<env>
     sudo -u postgres pg_restore -p 5433 -d moashir_<env> /root/moashir_<env>.pg16.dump
     ```
  4. set `DB_PORT=5433` in the environment's `.env`. Every kit command reads the port from there. Then run
     `provision.sh system` (grants and checks), deploy, smoke and `restore-test`;
  5. after a week, drop the Moashir databases and roles from the 16 cluster. Never drop or upgrade the 16 cluster
     itself while other sites use it.

## PDF renderer (certificates, feedback reports, receipts)

Certificates, feedback reports and payment receipts are printed by a pinned Chrome for Testing
`chrome-headless-shell` driven by `chrome-php/chrome`. It renders locally only:
- scripts are disabled, every network scheme is blocked and the host resolver answers nothing;
- fonts and images are inlined;
- one document is printed at a time per environment (`Cache::funnel`, Redis).

The Chrome sandbox is never turned off (`--no-sandbox` is not used). Ubuntu 24.04 restricts unprivileged user
namespaces, so the kit installs an AppArmor profile that allows them for this binary path only. The global switch
`kernel.apparmor_restrict_unprivileged_userns` is never changed.

`provision.sh system` installs it; `provision.sh pdf-renderer [staging|production|all]` runs only this part:

1. installs the missing libraries (listed in `CHROME_PACKAGES`, `deploy/lib/provision-pdf.sh`), `unzip` and
   `poppler-utils`;
2. downloads `chrome-headless-shell-linux64.zip` of `CHROME_HEADLESS_VERSION`. It verifies
   `CHROME_HEADLESS_SHA256` (nothing is installed on a mismatch) and unpacks root-owned into
   `/opt/moashir/chrome-headless-shell/<version>`;
3. checks with `ldd` that no library is missing, then writes and loads the AppArmor profile. It switches the
   `current` symlink atomically, starts the binary once as each environment user, and keeps the current and the
   previous version;
4. sets `PDF_RENDERER=chromium`, `PDF_CHROME_BINARY` and `PDF_RENDER_TIMEOUT_SECONDS=60` in each `.env` when they
   are empty.

Then deploy so the configuration cache picks the variables up. The smoke checks render the sample documents and
require `Tagged: yes` from `pdfinfo` and every font embedded in `pdffonts`.

### Monthly version bump (at least monthly, and on every Chrome security release)

1. Read the Stable version from
   `https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json`.
2. Download `https://storage.googleapis.com/chrome-for-testing-public/<version>/linux64/chrome-headless-shell-linux64.zip`
   and compute `sha256sum` (or `shasum -a 256`).
3. Set `CHROME_HEADLESS_VERSION` and `CHROME_HEADLESS_SHA256` in `deploy/lib/provision-pdf.sh`; keep the change in
   version control.
4. `deploy/deploy.sh staging sync-kit`, then on the server `bash /opt/moashir/deploy/provision.sh pdf-renderer staging`
   and `deploy/deploy.sh staging smoke`. Open one certificate and one Arabic feedback report and check the shaping.
5. When staging is good: `provision.sh pdf-renderer production` and `deploy/deploy.sh production smoke`.

Rollback to the previous version:

```
ln -sfn /opt/moashir/chrome-headless-shell/<previous version> /opt/moashir/chrome-headless-shell/current.next
mv -Tf /opt/moashir/chrome-headless-shell/current.next /opt/moashir/chrome-headless-shell/current
```

Then run `deploy/deploy.sh <env> smoke`.

## Payment by bank transfer (Task-59)

- Release order: deploy the backend (migrations add `bank_accounts`, `bank_transfers`, the payment `channel` and the
  `under_review` / `rejected` statuses), then run `provision.sh nginx-final` on each environment. The new Nginx
  template sends `POST /api/v1/payments` (a bank-transfer request carries its receipt) to the evidence-size location,
  and the SPA CSP gains `frame-src 'self' blob:` for the inline PDF preview of transfer receipts. Without
  `nginx-final`, a receipt larger than 2 MB is refused with `413 request.too_large`.
- Permissions: every deployment runs `moashir:permissions:sync`. The sync creates `finance.transfers.review` and gives
  it to the existing `finance_officer` role. It is a grantable permission: the System Administrator can grant it to
  other roles or users through the permissions matrix. Run `artisan moashir:permissions:sync` by hand only if a
  release was activated without the deploy script.
- Settings: `payment.channels` keeps its default `["electronic"]` until the System Administrator enables bank
  transfer on `/admin/settings/payment`. Enabling it needs at least one active bank account. No new `.env` variable.
- Rollback: the migration that adds the payment channel refuses to roll back once a bank-transfer payment exists, and
  the receipt file purpose refuses once a receipt is stored (payment records are kept for ten years). Use a forward
  fix instead.
- The demo installation (`demo-install`) adds one fictional account with the published registry example IBAN
  `SA0380000000608010167519`, labelled «مثال» / "example", and enables both payment channels. It belongs to no
  real bank account.

## Rollback

`deploy/deploy.sh <env> rollback` (or the CI deploy user) points `current` to the previous kept release, restores
its label (release id and commit) in `APP_RELEASE` / `SENTRY_RELEASE`, rebuilds the configuration cache and
restarts Horizon. Migrations are forward-only and are NOT reverted; a schema problem is fixed with a new migration.
Data recovery uses the backups (section "Backups and restore").

## Secrets scan (before every push)

`bash deploy/secrets-scan.sh` checks every file that would be pushed. In a repository that means tracked files and
untracked files that are not ignored; otherwise it walks the filesystem. It looks for:
- private keys, provider keys and tokens;
- `KEY=value` secrets;
- `.env` files and key stores;
- public IPv4 addresses.

It exits non-zero on any finding, and also when it could not list any file. Reviewed exceptions for fictional test
fixtures and version strings live in `deploy/secrets-scan.allow` (`<kind> <path glob>`, kind `ip` or `secret`,
`*` matches across directories).

## Continuous integration

`.github/workflows/ci.yml` runs on every push to `main` and every pull request:
- **backend**: Pint, Larastan, Rector, Pest (sequential) on PostgreSQL 17 with `max_locks_per_transaction = 256`,
  and `composer audit`;
- **frontend**: lint, the "tree unchanged" check, unit tests, the build and `npm audit`;
- **frontend-e2e**: Playwright critical flows in three shards on Chromium, in both `ar-SA` RTL and `en-US` LTR. It
  runs against the production build in full mock mode served by `vite preview`. Browsers are cached per Playwright
  version, and on failure the HTML report and traces are uploaded as `playwright-report-<shard>` (14 days);
- **deploy-kit**: `bash -n`, ShellCheck (`--severity=warning`; `deploy/lib/.shellcheckrc` exempts SC2034 because
  the library constants are used by the scripts that source them), `php -l` and the secrets scan.

Every action is pinned to a full commit SHA, and no job persists the checkout credentials:

| Action | Version | Commit |
|---|---|---|
| `actions/checkout` | v7.0.1 | `3d3c42e5aac5ba805825da76410c181273ba90b1` |
| `actions/setup-node` | v7.1.0 | `949feb2413d6458794dcd2491c4babbbce0c15c1` |
| `actions/cache` | v6.1.0 | `55cc8345863c7cc4c66a329aec7e433d2d1c52a9` |
| `actions/upload-artifact` | v7.0.2 | `cf430e030ddbb5b0abf93d22962f4752f3646cd9` |
| `shivammathur/setup-php` | 2.40.0 | `eb7c497e18156a6bbabfef1d3a82760b9eda3962` |

To update an action, look up the commit of the new release tag, replace the SHA and update this table. A Dependabot
configuration for `github-actions` can propose these updates.

Icons are the self-hosted TTS set (Task-53); the build needs no icon token or private registry.

## CI-gated deployment

`.github/workflows/deploy.yml`:
- **staging**: deploys automatically after a green `CI` run of a push to `main`, but only when the repository
  variable `MOASHIR_STAGING_AUTO_DEPLOY` is `true`;
- **staging or production**: by manual dispatch (`Actions` > `Deploy` > `Run workflow`) from `main`. The workflow
  first checks that the commit has a successful `CI` run;
- the job runs in the GitHub Environment of the target and connects as `moashir-deploy`. It records the commit in
  `APP_RELEASE` / `SENTRY_RELEASE`.

Until the secrets exist, the dispatch stops with a clear message, and the automatic trigger needs the variable.

One-time setup:
1. **Deploy key**: on a trusted workstation, create a dedicated key pair (ECDSA P-384 recommended, Ed25519 or RSA
   3072+ accepted). The private key goes only into GitHub. Copy the PUBLIC key to the server, then as root:

   ```
   MOASHIR_DEPLOY_PUBLIC_KEY_FILE=/root/moashir-deploy.pub bash /opt/moashir/deploy/provision.sh deploy-user
   ```

   This creates `moashir-deploy` with no password and installs the key with
   `command="…/remote/deploy-gate.sh",restrict`. The key can only `upload` a release into
   `/var/lib/moashir-deploy/incoming/<env>`, run `activate` / `smoke` / `rollback` and read the `kit-digest`, all
   through `/etc/sudoers.d/moashir-deploy`. That rule lets the user run exactly
   `/usr/bin/bash /opt/moashir/deploy/remote/release-entry.sh`, which validates every argument. Activation refuses
   releases that contain symbolic links or special files. The deploy user can never change the kit: when the kit
   in the commit differs from the server (`KIT_DIGEST`), the deploy stops and the operator runs
   `deploy/deploy.sh <env> sync-kit`.
2. **GitHub Environments** `staging` and `production`. For `production`: required reviewers (at least one person
   other than the author, "prevent self-review"), deployment branches `main` only.
3. **Environment secrets** (names only): `DEPLOY_HOST`, `DEPLOY_SSH_PRIVATE_KEY`, `DEPLOY_SSH_KNOWN_HOSTS` (the
   output of `ssh-keyscan` for the server, verified against the server's fingerprint out of band).
4. **Variables**: `MOASHIR_STAGING_AUTO_DEPLOY` (repository, `true` to enable), `DEPLOY_SSH_PORT` (optional),
   `VITE_SENTRY_DSN` (per Environment, once GlitchTip exists).
5. **Network**: the server must accept SSH from the runners (GitHub-hosted runner ranges, or a self-hosted runner
   inside the Kingdom).
6. **Root SSH**: once the deploy user works, root SSH is needed only for operator actions. On the in-Kingdom host,
   disable password logins and restrict or disable root login (`PermitRootLogin no` with named admin accounts and
   sudo, run the kit commands on the server). On the shared server this changes access for the other sites'
   administrators: the operator decides.

See `deploy/ADR-001-deployment-tooling.md` for why the kit is used instead of Deployer.

## Remove everything (uninstall)

Run as root on the server, in this order. Nothing here touches the other sites. First run
`bash /opt/moashir/deploy/provision.sh redis-release-shared <env>` for any environment still holding keys in the
shared Redis. Before that, check each index with `redis-cli -n <index> --scan --count 100 | head`.

```
systemctl disable --now moashir-backup@staging.timer moashir-backup@production.timer moashir-log-archive@staging.timer moashir-log-archive@production.timer
supervisorctl stop moashir-staging-horizon moashir-production-horizon
rm -f /etc/supervisor/conf.d/moashir-staging-horizon.conf /etc/supervisor/conf.d/moashir-production-horizon.conf
supervisorctl reread && supervisorctl update
rm -f /etc/cron.d/moashir-staging /etc/cron.d/moashir-production
rm -f /etc/nginx/sites-enabled/moashir-staging.conf /etc/nginx/sites-enabled/moashir-production.conf
rm -f /etc/nginx/sites-available/moashir-staging.conf /etc/nginx/sites-available/moashir-production.conf
rm -f /etc/nginx/snippets/moashir-*.conf /etc/nginx/conf.d/moashir-http.conf /etc/nginx/conf.d/moashir-default-tls.conf
nginx -t && systemctl reload nginx
certbot delete --cert-name moashir-staging
certbot delete --cert-name moashir-production
rm -f /etc/php/8.4/fpm/pool.d/moashir-staging.conf /etc/php/8.4/fpm/pool.d/moashir-production.conf
php-fpm8.4 -t && systemctl reload php8.4-fpm
systemctl disable --now moashir-redis@staging.service moashir-redis@production.service
rm -f /etc/systemd/system/moashir-redis@.service /etc/systemd/system/moashir-backup@.service /etc/systemd/system/moashir-backup@.timer /etc/systemd/system/moashir-log-archive@.service /etc/systemd/system/moashir-log-archive@.timer
systemctl daemon-reload
rm -rf /var/lib/moashir-redis-staging /var/lib/moashir-redis-production /etc/moashir-redis
sudo -u postgres psql -c 'DROP DATABASE moashir_staging' -c 'DROP DATABASE moashir_production'
sudo -u postgres psql -c 'DROP ROLE moashir_staging_owner' -c 'DROP ROLE moashir_staging_app' -c 'DROP ROLE moashir_staging_maintenance'
sudo -u postgres psql -c 'DROP ROLE moashir_production_owner' -c 'DROP ROLE moashir_production_app' -c 'DROP ROLE moashir_production_maintenance'
apparmor_parser -R /etc/apparmor.d/moashir-chrome-headless-shell && rm -f /etc/apparmor.d/moashir-chrome-headless-shell
rm -f /etc/sudoers.d/moashir-deploy /etc/logrotate.d/moashir-nginx /etc/logrotate.d/moashir-staging /etc/logrotate.d/moashir-production
shred -u /root/moashir-staging-demo-accounts.txt /root/moashir-production-demo-accounts.txt
userdel moashir-staging && userdel moashir-production && userdel moashir-deploy
rm -rf /var/www/moashir /var/log/moashir /opt/moashir /var/lib/moashir-staging /var/lib/moashir-production /var/lib/moashir-deploy /var/lib/moashir-state /var/backups/moashir
```

Keep `/etc/moashir` (backup keys) until the backups on the target have expired or been moved, then
`shred -u /etc/moashir/*/backup.key /etc/moashir/*/*.env && rm -rf /etc/moashir`. Removing `/opt/moashir` also
removes the PDF renderer; its libraries may stay. ClamAV and the swap file may stay. To remove them:
- `systemctl disable --now clamav-daemon clamav-freshclam`;
- `apt-get purge clamav clamav-daemon clamav-freshclam`;
- `rm -rf /etc/systemd/system/clamav-daemon.socket.d`;
- `swapoff /swapfile`, remove the `/swapfile` line from `/etc/fstab`, `rm /swapfile`.

## Known limits of this setup

- The current server is outside the Kingdom and shared with other sites. Real operation needs the in-Kingdom host.
- The Nginx worker user (`www-data`) is shared with the other sites: it can read the live Moashir Nginx logs and
  connect to the FPM sockets (group `www-data`). Token paths are masked in the access log, and archives are
  root only.
- On a shared IP, the TLS protocol negotiation follows the default server of another site (section "TLS").
- HTTP/2 is not enabled on purpose: enabling it on the shared `:443` socket would change the other sites.
- `open_basedir` is not set in the PHP-FPM pools. The separate users and private directories (0711 / 0750 / 600)
  already keep the environments apart. `open_basedir` needs a staging trial covering ClamAV, the PDF renderer
  and Redis sockets before it is enabled.
- The PDF renderer is a browser binary outside apt: it is only updated by the monthly bump above.
- Backup encryption keys live on the server (root only) with an offline escrow. A root compromise of the live
  server can read them; the live data is exposed in that case anyway.
