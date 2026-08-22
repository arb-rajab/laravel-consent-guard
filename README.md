# laravel-consent-guard

Eloquent-native consent tracking and tamper-evident audit logging, packaged
as a reusable Laravel dependency — installed with `composer require`, not
cloned and run.

## Why a package, and why Laravel again

This portfolio already has a Laravel *application* — [privacy-forge](https://github.com/arb-rajab/privacy-forge),
a self-hostable consent/DSAR/retention engine. Building another Laravel
project here is a deliberate repeat, not a default, for three reasons:

1. **The value being demonstrated only exists inside this framework.**
   privacy-forge's audit log and consent capture were built as
   application features, wired directly into one app's models and
   controllers. Extracting them into a package that other Laravel apps
   can drop in means leaning on exactly the extension points that make
   Laravel a framework rather than a library: Eloquent model events to
   observe writes without the caller remembering to call anything,
   Eloquent's attribute-casting system to make a tamper-evident audit
   entry look like a normal model attribute, and the HTTP middleware
   pipeline to attach consent checks to a route without touching its
   controller. None of that has an equivalent outside Laravel — a
   framework-agnostic PHP library would have to reinvent (badly) the
   hooks Laravel already provides, or drop the "transparent to the
   consuming app" property entirely.

2. **A different framework would not add a different kind of proof.**
   This portfolio's Laravel work is deliberately consolidated on one
   real, commercially-used version of one real framework rather than
   spread thin across novel ones chosen for variety's sake. What's
   still missing from that story is *ecosystem citizenship*: publishing
   to Packagist under semantic versioning, maintaining a real
   version-support matrix instead of pinning to whatever the demo app
   happens to run, and writing upgrade guidance when a supported major
   changes underneath consumers. Picking an unfamiliar framework here
   would trade that lesson for a weaker one ("I can also write Symfony
   bundles"), without making the existing Laravel work any more
   convincing.

3. **This is not privacy-forge with a different name.** It has no
   routes, no UI, and no database of its own to run migrations against
   in production — it ships model traits, a service provider, and
   (starting in a later session) an audit-log implementation that a
   *host* application's own database persists. It is developed and
   tested against a matrix of Laravel and PHP versions via
   [Orchestra Testbench](https://packages.laravel-testbench.com/) — a
   throwaway, in-memory Laravel application constructed purely to run
   this package's tests — and is never deployed anywhere as a running
   service in its own right.

## What this is (and isn't) right now

This repository is currently **governance and skeleton only**: a real
Composer package structure, a passing (placeholder) test suite proven to
run under Testbench, and CI enforcing lint/static-analysis/tests/security
scanning across a Laravel version matrix. There is no consent-guard or
audit-log feature code yet — see Roadmap below.

## Requirements

- PHP 8.2+
- Laravel 12.x or 13.x (via `illuminate/*` components)

## Installation

```bash
composer require arb-rajab/laravel-consent-guard
```

The package's service provider is auto-discovered; no manual registration
is required.

## Development

```bash
git clone https://github.com/arb-rajab/laravel-consent-guard.git
cd laravel-consent-guard
composer install

composer test      # Pest, via Orchestra Testbench
composer lint      # Laravel Pint (add :fix to auto-fix)
composer analyse   # Larastan / PHPStan, level 8
```

There is no `.env`, no database service, and no application to boot by
hand — `composer test` spins up and tears down an in-memory Testbench
application per run.

## Roadmap

This package is being built across four sessions of a documented,
session-based workflow, the same discipline used elsewhere in this
portfolio:

- **Session 1 (this one): governance and skeleton.** Composer package
  structure, Testbench-backed test harness with a passing placeholder
  test, CI (lint, static analysis, tests, Laravel version matrix,
  secret scanning, CodeQL, dependency vulnerability scanning), and the
  governance files a real open-source package needs before its first
  line of feature code.
- **Session 2: audit-log extraction.** Generalise privacy-forge's
  tamper-evident, hash-chained audit log into a framework-agnostic
  (within Laravel) trait/listener pair driven by Eloquent model events,
  with its own migration and test suite.
- **Session 3: consent-guard middleware and casts.** The
  consent-tracking half: a `HasConsent`-style Eloquent trait, an
  attribute cast for consent state, and route middleware that enforces
  a consent check ahead of a controller.
- **Session 4: Packagist publishing and upgrade documentation.**
  Tagged `v1.0.0`, published to Packagist, with an `UPGRADE.md` for the
  Laravel-major boundaries the CI matrix already covers.

## License

[MIT](LICENSE) — see [`SECURITY.md`](SECURITY.md) for vulnerability
reporting and [`CONTRIBUTING.md`](CONTRIBUTING.md) for the contribution
workflow.
