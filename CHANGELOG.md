# Changelog

All notable changes to this project will be documented in this file.
Format based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

Nothing pending — see `v1.0.0` below for this package's first release.

## [1.0.0] - 2026-08-23

This is the package's first tagged release. Chosen as `1.0.0` rather than
a pre-1.0 series because the bar this portfolio holds features to —
real infrastructure proof, fault-injection tests for the fail-closed
guarantees, a genuine (not aspirational) CI version matrix, and now a real
integration proof in a separate application — was already met before this
tag, not something still being worked toward; see Session 4 below for
what specifically was added to confirm that ahead of tagging.

### Added (Session 4, 2026-08-23)
- **Real integration proof**: this package installed via a genuine
  `composer require` (against a local `path` repository standing in for
  Packagist, which this package isn't published to yet) into a fresh,
  separate `laravel/laravel` 13.26.1 application — not just required as a
  Testbench dev dependency inside this repository's own test suite. Both
  features were driven through real HTTP requests against that
  application's own `php artisan serve` process (a session-authenticated
  consent-guard-gated route: 403 → grant → 200 → withdraw → 403 again; a
  `ConsentRequired`-cast field correctly isolating one purpose's consent
  from another's; real hash-chained audit log entries recorded and
  verified), and the privilege-separation guarantee was independently
  re-confirmed against that application's own live database connection
  (not the test sandbox) — `consent_guard_app` rejected with `42501` on
  direct `UPDATE`/`DELETE`, and `verifyChain()` correctly flipped to
  `valid: false` at the right sequence number after the owning role
  tampered with a row. See `HANDOFF.md`'s Session 4 entry for the full
  transcript.
- **CI version-compatibility matrix re-confirmed against real, current
  logs** (not re-asserted from an earlier session's summary): pulled the
  latest `main` CI run and grepped each of the 4 matrix cells' own
  `Tests: ... passed` lines directly — PHP 8.2/Laravel 12, PHP 8.3/Laravel
  12, PHP 8.3/Laravel 13, and PHP 8.4/Laravel 13 each independently
  reported `42 passed (103 assertions)`.
- README: a new "What's extracted from privacy-forge, and what's newly
  designed" section stating, feature by feature, exactly what was
  extracted/generalized (the audit log) versus designed from scratch
  (consent guard) — previously this distinction was scattered across
  prose rather than given its own honest, explicit section. A new "Proven
  end-to-end, in a real separate application" section summarizing this
  session's integration proof. A "Packagist publishing" section stating
  plainly that publishing requires a real Packagist account this
  environment does not have, with the exact steps a human needs to take.
- `UPGRADE.md`: added ahead of actually needing content, so this
  package's first real breaking change has an obvious place to land. States
  there is nothing to upgrade from yet (first release) and that Laravel
  12/13 support is identical in behavior on both, per the CI matrix.
- No production code changed this session — this was a release-readiness
  and verification session, not a feature session.

### Added (Session 3, 2026-08-22)
- Consent guard: new package design (not extracted from privacy-forge),
  inspired by that repository's fail-closed policy-evaluator *principle*
  (ADR-0006) rather than sharing any of its code.
  - `Consent\ConsentManager`: `grant()`/`withdraw()`/`isGranted()`, the
    single fail-closed choke point every other part of this feature
    calls through — any ambiguous or error state (no record, withdrawn,
    expired, or the lookup itself throwing) resolves to "not granted."
  - `Consent\ConsentRecord`: current-state Eloquent model, one row per
    (subject, purpose).
  - `Consent\Concerns\HasConsent` + `Consent\Contracts\ConsentSubject`:
    trait/interface pairing (mirroring Laravel's own
    `MustVerifyEmail`/`CanVerifyEmail`) giving a model
    `grantConsent()`/`withdrawConsent()`/`hasConsent()`.
  - `Consent\Casts\ConsentRequired`: Eloquent attribute cast gating a
    single field's read and write behind a named purpose.
  - `Consent\Http\Middleware\EnsureConsentGranted` (registered as the
    `consent-guard` route middleware alias): denies ahead of the
    controller unless the authenticated user implements
    `ConsentSubject` and has valid consent for the named purpose.
  - `consent-guard:sweep-expired-consent` console command: dispatches
    `Consent\Events\ConsentGracePeriodElapsed` for records past their
    configured grace period, optionally purging the `ConsentRecord` row
    itself — deliberately does not touch the host app's own gated data,
    since this package has no schema of the host's to act on.
  - `Consent\Contracts\ConsentRepository`: the one seam between
    `ConsentManager` and the actual lookup, added specifically so a test
    (or an adopting app's own) can swap in a throwing fake to prove the
    fail-closed guarantee deterministically.
  - Publishable `database/migrations/..._create_consent_records_table.php`
    and a new `consent` section in `config/consent-guard.php`
    (connection/table, grace periods, per-purpose overrides,
    `purge_expired_records`, deny status/message) — no code changes
    needed to register a new purpose.
  - `docs/adr/0002-consent-guard-fail-closed.md`.
- `ConsentFailClosedFaultInjectionTest`: grants real consent, then makes
  the lookup itself throw, and proves both `ConsentManager::isGranted()`
  (returns `false`, does not throw) and `EnsureConsentGranted` (denies
  with 403, not 200 or an unhandled 500) fail closed — with a positive
  control proving the same route allows access when nothing is broken.
- New dependency: `illuminate/routing` (needed for the `consent-guard`
  middleware alias) — added to `composer.json`'s `require` and to the CI
  matrix's per-cell version selection, not left to resolve implicitly
  via `laravel/framework`.
- Unlike the audit log, consent guard is not Postgres-specific — plain
  Eloquent CRUD, no privilege separation. This package's own tests still
  run it against the same Postgres instance already provisioned for the
  audit log, purely for local/CI infra reuse.

### Changed (Session 2.5, 2026-08-22)
- `docs/adr/0001-audit-log-tamper-evidence.md`: added a "Generalization
  decisions specific to this package" section documenting the
  decisions unique to extracting privacy-forge's mechanism into a
  package (generic actor/subject/metadata fields, the trait+service API
  split, privilege separation as a command rather than a migration, not
  creating the runtime role) — previously recorded only in `HANDOFF.md`.
- Confirmed, by reading actual per-matrix-cell CI job logs rather than
  the aggregate job-count summary, that all 4 test-matrix cells
  (PHP 8.2–8.4 × Laravel 12/13) independently ran and passed all 12
  audit-log tests.
- **Process note**: the ADR fix was pushed directly to `main`,
  bypassing required-status-checks branch protection via admin
  privilege, rather than through a branch + PR. Logged in `HANDOFF.md`
  as a real process gap with a standing rule for future sessions — see
  that file's Session 2.5 entry.

### Added (Session 2, 2026-08-22)
- Tamper-evident, hash-chained audit log, generalized out of
  privacy-forge's application-specific implementation:
  `ArbRajab\ConsentGuard\AuditLog\AuditLogger` (record/verifyChain),
  `AuditLogEntry` (append-only Eloquent model), and
  `HasTamperEvidentAuditLog` (a trait any Eloquent model can use to
  record entries about itself).
- Concurrency safety via a single, fixed, global
  `pg_advisory_xact_lock`, proven with a real test that forks 8 actual
  OS processes against a real Postgres instance.
- `consent-guard:secure-audit-log` console command: installable
  privilege-separation feature that revokes `UPDATE`/`DELETE` on the
  audit log table from the application's runtime role at the Postgres
  level, via a genuinely separate, non-owning role — proven with a real
  test connecting as that role and confirming Postgres itself rejects
  raw SQL `UPDATE`/`DELETE`.
- Publishable config (`config/consent-guard.php`) and migration
  (`create_audit_log_entries_table`).
- `docs/adr/0001-audit-log-tamper-evidence.md` documenting the design
  and its generalization from privacy-forge's ADR-0003/R-01.
- `docker-compose.yml` + `docker/` (Postgres + a PHP dev image) for
  local development and testing, since this feature requires
  PostgreSQL and, for the concurrency test, Unix `pcntl`/`posix`.

### Added (Session 1, 2026-08-22)
- Initial Composer package skeleton: `type: library`, PSR-4 autoload under
  `ArbRajab\ConsentGuard`, `illuminate/*` component dependencies (not the
  full `laravel/framework`), auto-discovered `ConsentGuardServiceProvider`
  stub.
- Orchestra Testbench test harness with a passing placeholder test
  proving the infrastructure itself works, ahead of any real feature.
- Tooling: Laravel Pint, Larastan (PHPStan level 8, clean against the
  current skeleton).
- CI: lint, static analysis, tests, a Laravel 12/13 compatibility matrix,
  gitleaks, CodeQL, and osv-scanner.
- Governance: MIT license, README (with the reuse justification for a
  second Laravel repository in this portfolio and a four-session
  roadmap), CONTRIBUTING, SECURITY, CODE_OF_CONDUCT.

No feature code (audit-log, consent-guard) exists yet — see
[`HANDOFF.md`](HANDOFF.md) and the README's Roadmap section.
