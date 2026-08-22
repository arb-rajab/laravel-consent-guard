# Contributing

This is a solo portfolio project, built session-by-session (see
[`HANDOFF.md`](HANDOFF.md) for the current session's state). External
contributions are welcome once the package reaches a stable `v1.0.0`, but
please read this first.

## Before contributing

1. Check the Roadmap in [`README.md`](README.md) — this is currently
   Session 1 of 4 (governance and skeleton only). Feature PRs before the
   audit-log and consent-guard sessions land will be declined as
   premature, not because the idea is unwelcome.
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

This is a Composer library, not an application — there is no `.env`, no
database service to run, and nothing to deploy. Tests run against an
in-memory Laravel application constructed by
[Orchestra Testbench](https://packages.laravel-testbench.com/).

```bash
git clone https://github.com/arb-rajab/laravel-consent-guard.git
cd laravel-consent-guard
composer install
```

```bash
composer test      # Pest, via Testbench
composer lint       # Laravel Pint (add :fix to auto-fix)
composer analyse    # Larastan / PHPStan, level 8
```

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
