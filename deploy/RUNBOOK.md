# Moashir review / demo deployment runbook

Two environments of the same code run on one shared Ubuntu 24.04 server:

| Environment | Domain | `APP_ENV` | Database | Redis DB (data / cache) |
|---|---|---|---|---|
| staging | `staging.moashir.ttshub.live` | `staging` | `moashir_staging` | 10 / 11 |
| production (client demo) | `moashir.ttshub.live` | `production` | `moashir_production` | 12 / 13 |

Both hold FICTIONAL data only. No real personal data and no live provider keys may be entered.

## Temporary hosting exception

This server is outside the Kingdom of Saudi Arabia. It is used for review and the client demo only, with
fictional data, no real personal data and no live provider keys. The platform moves to in-Kingdom hosting
before real operation; at that point this whole setup is removed (see "Remove everything").

## What runs where

| Item | Location |
|---|---|
| System user | `moashir` (no login shell, home `/var/lib/moashir`) |
| Releases | `/var/www/moashir/<env>/releases/<UTC timestamp>/{backend,frontend}`, `current` symlink, 3 kept |
| Shared files | `/var/www/moashir/<env>/shared/.env` (mode 600), `shared/storage`, `shared/tmp` |
| Database owner credentials | `/etc/moashir/<env>/database-owner.env` (root only, used for migrations) |
| PHP-FPM | pool `moashir-<env>`, socket `/run/php/php8.4-fpm-moashir-<env>.sock`, `pm = ondemand` |
| Queue | Supervisor program `moashir-<env>-horizon` |
| Scheduler | `/etc/cron.d/moashir-<env>` (every minute, user `moashir`) |
| Logs | `/var/log/moashir/<env>/` (rotated daily, 14 kept), Laravel logs in `shared/storage/logs` |
| Nginx | `/etc/nginx/sites-available/moashir-<env>.conf`, snippets `/etc/nginx/snippets/moashir-*`, `/etc/nginx/conf.d/moashir-http.conf` |
| Certificates | Let's Encrypt `moashir-staging`, `moashir-production` (webroot `/var/www/moashir/acme`) |
| ClamAV | `clamav-daemon` + `clamav-freshclam`, socket `/var/run/clamav/clamd.ctl` (group `clamav`, mode 660) |
| Deployment kit | `/opt/moashir/deploy` (root owned); Composer in `/opt/moashir/bin` when the server has none |
| Demo accounts | `/root/moashir-<env>-demo-accounts.txt` (root only, mode 600) |

Shared services are never restarted: Nginx and PHP-FPM are only reloaded after `nginx -t` / `php-fpm8.4 -t`,
Supervisor only registers the Moashir programs, PostgreSQL and Redis receive no configuration change.
Other sites on the server are not touched.

## Before you start (operator workstation)

1. Tools: `ssh`, `tar`, `curl`, Node 24 + npm (SPA build). Root SSH access to the server with a key.
2. Export the server address for this shell only; it is never written into the repository:

   ```
   export DEPLOY_HOST=<server address>
   ```

   Optional: `DEPLOY_SSH_USER` (default `root`), `DEPLOY_SSH_PORT` (default `22`),
   `FONTAWESOME_NPM_AUTH_TOKEN` (Font Awesome Pro icons in the build; without it icons degrade gracefully).
3. Both DNS records (`staging.moashir.ttshub.live`, `moashir.ttshub.live`) must point to the server.
4. Before every push run `bash deploy/secrets-scan.sh` from the repository root (see "Secrets scan").

## Exact order of the first installation

1. **Upload the kit**: `deploy/deploy.sh staging sync-kit`
2. **Provision the server** (packages, 2 GiB swap, ClamAV + signatures, user, directories, PHP-FPM pools,
   databases and roles, `.env` files, Horizon programs, cron, log rotation):

   ```
   ssh root@$DEPLOY_HOST bash /opt/moashir/deploy/provision.sh system
   ```

   Passwords and keys are generated on the server (`openssl rand`) and written only to the `.env` files
   (mode 600) and to `/etc/moashir/<env>/database-owner.env` (root only). Nothing is printed. The first ClamAV
   signature download can take several minutes. If Redis requires a password that is not in
   `/etc/redis/redis.conf`, the script stops and asks for `REDIS_PASSWORD` in both `.env` files.
3. **Bootstrap Nginx** (HTTP only, ACME challenge for the two Moashir domains):
   `ssh root@$DEPLOY_HOST bash /opt/moashir/deploy/provision.sh nginx-bootstrap`
4. **Certificates for the two Moashir domains only**:
   `ssh root@$DEPLOY_HOST bash /opt/moashir/deploy/provision.sh certificates`
   If certbot has several accounts on this server, add `MOASHIR_CERTBOT_ACCOUNT=<account id>`; to register a new
   account with an address use `MOASHIR_CERTBOT_EMAIL=<address>` (both as environment variables on the server
   command). Renewal uses the existing certbot timer and reloads Nginx after `nginx -t`.
5. **Final Nginx** (HTTPS, SPA, `/api`, `/sanctum`, Apple Pay association path, headers):
   `ssh root@$DEPLOY_HOST bash /opt/moashir/deploy/provision.sh nginx-final`
6. **Deploy staging**: `deploy/deploy.sh staging` (builds the SPA in `staging` mode, uploads the release,
   `composer install --no-dev --optimize-autoloader`, `migrate --force` with the owner role, database grants,
   `moashir:permissions:sync`, criteria / regions / breached-password reference data, `config`, `route`, `view`,
   `event` caches, atomic switch of `current`, `queue:restart` + Horizon restart, keeps 3 releases, smoke checks).
   `db:seed` is never run.
7. **Demo data on staging**: `deploy/deploy.sh staging demo-install`
8. **Smoke checks on staging**: `deploy/deploy.sh staging smoke`, then the manual checks below.
9. **Deploy production**: `deploy/deploy.sh production`
10. **Demo data on production**: `deploy/deploy.sh production demo-install`
11. **Smoke checks on production**: `deploy/deploy.sh production smoke`, then the manual checks below.
12. **Super Admin** (the operator, interactively, once per environment):
    `deploy/deploy.sh staging super-admin` and `deploy/deploy.sh production super-admin`.
    The script asks for the e-mail address and the name, runs `moashir:super-admin grant` (which asks for the
    typed confirmation) and restores the configuration cache. The demo command never creates a Super Admin.

Later deployments: steps 6 and 9 only (`deploy/deploy.sh <env>`).

## Manual smoke checks (both environments)

- Open the site in Arabic and English; the language switch works; no browser console errors.
- Sign in with a staff demo account: the TOTP enrolment is requested (MFA is mandatory for staff).
- Sign in with an entity demo account; the accredited request created by the TEST payment is visible.
- Payment checkout in TEST mode only after the client entered TEST keys in the payment settings screen. Open the
  browser console on the checkout page: any Content-Security-Policy violation names an origin that must be added to
  `/etc/nginx/snippets/moashir-spa-headers.conf` (source: `deploy/nginx/moashir-spa-headers.conf`) followed by
  `nginx -t && systemctl reload nginx`. The policy allows the Moyasar form origins; Apple Pay or a Font Awesome kit
  may need more origins.
- Upload an evidence file (scanned by ClamAV; the EICAR test string must be refused).

## Demo data

- Command: `php artisan moashir:demo:install --accounts-file=<absolute path outside the application>`, wrapped by
  `deploy/deploy.sh <env> demo-install`.
- Runs only when `APP_DEMO_DATA=true` (set in both templates) and refuses (with a critical log line
  `platform.demo_data_refused`) when an entity exists that the demo command did not create, or when the demo is
  already installed.
- Creates: one staff account per staff role (MFA required, TOTP enrolment at first sign-in), three fictional
  entities with their owner accounts, demo terms and privacy texts when none are published, the accredited price when
  none is set, one draft award cycle and two TEST-mode payments (one paid with a TEST receipt, one open order).
- Passwords are random and are written only to `/root/moashir-<env>-demo-accounts.txt` (mode 600). Read the file on
  the server; never paste it into chat, tickets or the repository.
- Outgoing e-mail (sign-in codes, invitations) uses the `log` mailer and is written to
  `shared/storage/logs/mail-<date>.log` (mode 600, kept 7 days). Switch `MAIL_MAILER=settings` once SMTP is
  configured in the e-mail settings screen.

### Rotate the demo passwords

`deploy/deploy.sh <env> demo-rotate-passwords` issues new random passwords for every demo account, ends their
sessions and replaces `/root/moashir-<env>-demo-accounts.txt`.

## Rollback

`deploy/deploy.sh <env> rollback` points `current` to the previous kept release, rebuilds the configuration cache
and restarts Horizon. Migrations are forward-only and are NOT reverted; a schema problem is fixed with a new
migration and a new deployment. Data recovery uses the backups.

## Secrets scan (before every push)

`bash deploy/secrets-scan.sh` checks every file that would be pushed (tracked and untracked files that are not
ignored, or a filesystem walk when the folder is not a repository) for private keys, provider keys and tokens,
`KEY=value` secrets, `.env` files, key stores and public IPv4 addresses. It exits non-zero on any finding. Reviewed
exceptions for fictional test fixtures and version strings live in `deploy/secrets-scan.allow` (`<kind> <path glob>`,
kind `ip` or `secret`).

## Continuous integration

`.github/workflows/ci.yml` runs on every push to `main` and every pull request: backend (Pint, Larastan, Rector,
Pest sequential on PostgreSQL 16 with `max_locks_per_transaction = 256`, `composer audit`, Composer process timeout
1200 s), frontend on Node 24 (lint, unit tests, build, `npm audit`) and the deployment kit (`bash -n`, ShellCheck,
secrets scan). Optional repository secret: `FONTAWESOME_NPM_AUTH_TOKEN`.

### Adding automatic deployment later

1. Create a dedicated deploy key pair on a trusted workstation; add the public key to `/root/.ssh/authorized_keys`
   on the server (or to a dedicated deploy user with the same rights).
2. Repository secrets: `DEPLOY_SSH_KEY` (private key), `DEPLOY_KNOWN_HOSTS` (output of `ssh-keyscan` for the
   server, verified out of band), `DEPLOY_HOST`.
3. Add `.github/workflows/deploy.yml` triggered by `workflow_run` of `CI` on `main` with `conclusion == success`
   (staging) and by `workflow_dispatch` with a protected environment and a required reviewer (production). The job
   writes the key and `known_hosts` into `~/.ssh` (mode 600), exports `DEPLOY_HOST` and runs
   `bash deploy/deploy.sh staging` (or `production`).

## Remove everything (uninstall)

Run as root on the server, in this order. Nothing here touches the other sites.

```
supervisorctl stop moashir-staging-horizon moashir-production-horizon
rm -f /etc/supervisor/conf.d/moashir-staging-horizon.conf /etc/supervisor/conf.d/moashir-production-horizon.conf
supervisorctl reread && supervisorctl update
rm -f /etc/cron.d/moashir-staging /etc/cron.d/moashir-production
rm -f /etc/nginx/sites-enabled/moashir-staging.conf /etc/nginx/sites-enabled/moashir-production.conf
rm -f /etc/nginx/sites-available/moashir-staging.conf /etc/nginx/sites-available/moashir-production.conf
rm -f /etc/nginx/snippets/moashir-*.conf /etc/nginx/conf.d/moashir-http.conf
nginx -t && systemctl reload nginx
certbot delete --cert-name moashir-staging
certbot delete --cert-name moashir-production
rm -f /etc/php/8.4/fpm/pool.d/moashir-staging.conf /etc/php/8.4/fpm/pool.d/moashir-production.conf
php-fpm8.4 -t && systemctl reload php8.4-fpm
sudo -u postgres psql -c 'DROP DATABASE moashir_staging' -c 'DROP DATABASE moashir_production'
sudo -u postgres psql -c 'DROP ROLE moashir_staging_owner' -c 'DROP ROLE moashir_staging_app' -c 'DROP ROLE moashir_staging_maintenance'
sudo -u postgres psql -c 'DROP ROLE moashir_production_owner' -c 'DROP ROLE moashir_production_app' -c 'DROP ROLE moashir_production_maintenance'
redis-cli -n 10 FLUSHDB && redis-cli -n 11 FLUSHDB && redis-cli -n 12 FLUSHDB && redis-cli -n 13 FLUSHDB
rm -rf /var/www/moashir /var/log/moashir /etc/moashir /opt/moashir /etc/logrotate.d/moashir
shred -u /root/moashir-staging-demo-accounts.txt /root/moashir-production-demo-accounts.txt
userdel -r moashir
```

Check each Redis index with `redis-cli -n <index> --scan --count 100 | head` before `FLUSHDB`: only keys starting
with `moashir_` may be present (add `REDISCLI_AUTH` when Redis has a password). ClamAV and the swap file may stay;
to remove them: `systemctl disable --now clamav-daemon clamav-freshclam`,
`apt-get purge clamav clamav-daemon clamav-freshclam`, `rm -rf /etc/systemd/system/clamav-daemon.socket.d`,
`swapoff /swapfile`, remove the `/swapfile` line from `/etc/fstab`, `rm /swapfile`.

## Known limits of this demo setup

- Backups (`backup:run`, daily) are encrypted but stay on this server (`shared/storage/app/private`); off-server,
  in-Kingdom backups are required before real operation.
- The Nginx worker user is shared with the other sites; the FPM sockets accept only that group.
- On a shared IP address the TLS protocol negotiation may follow the default server of another site.
- HTTP/2 is not enabled on purpose: enabling it on the shared `:443` socket would change the other sites.
- Receipt and report PDFs answer 503 until the Arabic PDF library is selected (`PDF_RENDERER` stays unset).
