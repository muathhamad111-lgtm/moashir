# ADR-001: Deployment tooling — Bash kit with a CI-gated workflow instead of Deployer

- Status: accepted (records a deviation from the approved architecture)
- Date: 2026-11-04
- Scope: `deploy/`, `.github/workflows/deploy.yml`

## Context

The approved architecture names "Deployer via GitHub Actions after CI passes" as the deployment tool: zero-downtime
releases, one-command rollback, triggered only after CI is green.

The project already ships a Bash deployment kit (`deploy/`). It provisions the host, deploys releases in the
Deployer-compatible layout (`releases/<id>`, `shared/`, an atomic `current` symlink, 3 releases kept) and rolls
back. It also runs work that Deployer recipes do not cover, all of it root-side and audited:

- one OS user, private Redis and PHP-FPM pool per environment;
- database roles with append-only grants;
- ClamAV, the pinned PDF renderer and its AppArmor profile;
- encrypted off-server backups with a scripted restore test;
- log retention, and the demo / real-operation posture guards.

The security review asked that production deploys stop being root sessions from a workstation. They must run from
CI after green checks, through a protected environment with reviewers and a non-root deploy user with narrow
privileges.

Adding Deployer means a PHP dependency in the deploy path: `deployer/deployer` in a Composer manifest plus its
recipes. Every task would need two tools that both know the server layout.

## Decision

1. Keep the Bash kit as the only deployment tool. Do not add Deployer.
2. Meet the intent of the approved line ("deploy only after CI passes, from GitHub Actions") with
   `.github/workflows/deploy.yml`:
   - staging deploys automatically after a green `CI` run on `main`, opt-in through the repository variable
     `MOASHIR_STAGING_AUTO_DEPLOY`;
   - production deploys only by manual dispatch from `main`. The workflow checks through the GitHub API that the
     same commit has a successful `CI` run, and the job runs in the `production` GitHub Environment with required
     reviewers;
   - every action is pinned to a full commit SHA, and the commit id is recorded in `APP_RELEASE`.
3. CI connects as `moashir-deploy`, not root. Its key is bound to a forced command (`deploy/remote/deploy-gate.sh`)
   that accepts only five verbs: `upload`, `activate`, `smoke`, `rollback` and `kit-digest`. Root work runs through
   one sudo rule (`deploy/remote/release-entry.sh`, strict argument checks). The deploy user can never change the
   root-owned kit. CI refuses to deploy when the kit on the server differs from the commit (`KIT_DIGEST`), and
   only the operator updates the kit (`sync-kit`).
4. Provisioning, posture changes, backups / restore tests and the Super Admin stay operator actions over root SSH
   (or an admin account with sudo on the server).

## Consequences

- No new package and no second tool. CI and the operator workstation run the same scripts, which are checked by
  `bash -n`, ShellCheck and the secrets scan in CI.
- The release layout stays Deployer-compatible. Moving to Deployer later needs no server change, only a new
  ADR and recipes that call the same remote scripts.
- The kit must cover what Deployer would give for free (locking, release pruning, rollback). Concurrency is
  limited per environment by the workflow's `concurrency` group, and the kit keeps 3 releases.
- The handover package must list this deviation: architecture document, security controls matrix
  (SEC-14 → `deploy.yml`, deploy gate, sudo rule, Environment reviewers).
- Deploying from GitHub-hosted runners means the build runs outside the Kingdom; it handles source code only, no
  personal data. A self-hosted runner inside the Kingdom can replace them without changing the workflow logic.
