# Contributing

This is a solo portfolio project, built session-by-session (see
[`HANDOFF.md`](HANDOFF.md) for the current session's state). External
contributions are welcome once the package reaches a stable `v1.0.0`, but
please read this first.

## Before contributing

1. Check the Roadmap in [`README.md`](README.md) — Session 2 (audit-log)
   has landed; consent-guard (Session 3) has not. Feature PRs for it will
   be declined as premature, not because the idea is unwelcome.
2. Open an issue before a large PR.

## Workflow

- **Branching:** trunk-based. Branch from `main` as `feat/<slug>`,
  `fix/<slug>`, `chore/<slug>`, or `sec/<slug>`.
- **Commits:** [Conventional Commits](https://www.conventionalcommits.org/)
  (`feat:`, `fix:`, `docs:`, `test:`, `ci:`, `refactor:`, `chore:`, `sec:`).
- **Pull requests:** must pass CI (lint, static analysis, tests across the
  Laravel version matrix, secret scanning, dependency scanning) before
  review.

## Development setup

This is a Composer library, not an application — there is nothing to
deploy. The audit-log feature (Session 2) is Postgres-specific and its
concurrency test forks real OS processes (`pcntl`/`posix`, Unix-only), so
tests now need a real Postgres instance and, for that one test, a
Unix-like PHP. A `docker-compose.yml` provides both:

```bash
git clone https://github.com/arb-rajab/laravel-consent-guard.git
cd laravel-consent-guard

docker compose up -d --build
docker compose exec php composer install
```

```bash
docker compose exec php composer test      # Pest, via Testbench
docker compose exec php composer lint      # Laravel Pint (add :fix to auto-fix)
docker compose exec php composer analyse   # Larastan / PHPStan, level 8
```

If your own machine already has PHP 8.2+ with `pdo_pgsql`/`pgsql`
(and, for the concurrency test, `pcntl`/`posix`) plus a reachable
Postgres, you can run these three commands directly without Docker —
set `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/
`DB_OWNER_USERNAME`/`DB_OWNER_PASSWORD` to match (see `tests/TestCase.php`
for the defaults these fall back to, and
`docker/postgres/init/01-create-app-role.sql` for how the restricted test
role is provisioned). On the concurrency test's Unix requirement: it
`markTestSkipped`s itself when `pcntl`/`posix` aren't loaded, so a
Windows/no-Docker setup still runs the rest of the suite — just without
that one proof.

These three commands are exactly what CI runs, per Laravel version in the
matrix (`.github/workflows/ci.yml`) — if they pass locally on your default
PHP/Laravel combination, run them again after `composer update` with an
older `orchestra/testbench` constraint if you need to reproduce a
matrix-specific failure.

## Reporting security issues

See [`SECURITY.md`](SECURITY.md) — do not use public issues for
vulnerabilities.

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
