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

This repository now ships one real feature: a **tamper-evident,
hash-chained audit log**, generalized out of privacy-forge's
application-specific implementation into something any Eloquent model in
any Laravel app can use (see [`docs/adr/0001-audit-log-tamper-evidence.md`](docs/adr/0001-audit-log-tamper-evidence.md)
for the design and its reasoning). There is no consent-guard feature yet
— see Roadmap below.

## Requirements

- PHP 8.2+
- Laravel 12.x or 13.x (via `illuminate/*` components)
- **PostgreSQL**, for the audit-log feature specifically — it relies on
  `pg_advisory_xact_lock` for concurrency safety and on real role-level
  `GRANT`/`REVOKE` for privilege separation, neither of which has a
  database-agnostic equivalent this package could fall back to. Stated
  plainly rather than glossed over: this feature does not work on
  MySQL/SQLite.

## Installation

```bash
composer require arb-rajab/laravel-consent-guard
```

The package's service provider is auto-discovered; no manual registration
is required.

## Tamper-evident audit log

Any Eloquent model can record an entry about itself:

```php
use ArbRajab\ConsentGuard\AuditLog\Concerns\HasTamperEvidentAuditLog;

class Order extends Model
{
    use HasTamperEvidentAuditLog;
}

$order->recordAuditEntry('order.shipped', ['carrier' => 'ups'], actorType: 'user', actorId: $user->id);
```

or call the service directly for entries that aren't about a specific
model:

```php
app(\ArbRajab\ConsentGuard\AuditLog\AuditLogger::class)->record(
    actorType: 'system',
    actorId: null,
    action: 'nightly-export.completed',
    subjectType: 'export_batch',
    subjectId: $batch->id,
);
```

Every entry is chained to the one before it
(`entry_hash = sha256(prev_entry_hash + this_entry's_fields)`);
`AuditLogger::verifyChain()` replays the whole chain and reports the first
entry, if any, whose stored hash no longer matches its content.

### Installing it

```bash
# 1. Publish config + migration
php artisan vendor:publish --tag=consent-guard-config
php artisan vendor:publish --tag=consent-guard-migrations

# 2. Run the migration via a connection authenticated as the role that
#    should OWN the audit log table (see config/consent-guard.php's
#    "owner_connection" — typically the same role your other migrations
#    already run as):
php artisan migrate --database=<owner-connection>

# 3. Lock the table down so the app's own runtime role can SELECT/INSERT
#    but never UPDATE/DELETE — enforced by Postgres itself, not this
#    package's application code:
php artisan consent-guard:secure-audit-log --owner-connection=<owner-connection>
```

Step 3 is the differentiated part: it requires the application's runtime
database role to be genuinely distinct from whatever role owns the
schema. A role revoking its own `UPDATE`/`DELETE` is not a real
protection — see the ADR for why.

## Development

This package's test suite now needs real PostgreSQL (see above) plus, for
the concurrency test, real forked OS processes (`pcntl`/`posix` — Unix
only). A `docker-compose.yml` is provided for exactly this:

```bash
git clone https://github.com/arb-rajab/laravel-consent-guard.git
cd laravel-consent-guard

docker compose up -d --build
docker compose exec php composer install

docker compose exec php composer test      # Pest, via Orchestra Testbench
docker compose exec php composer lint      # Laravel Pint (add :fix to auto-fix)
docker compose exec php composer analyse   # Larastan / PHPStan, level 8
```

If your own machine already has PHP 8.2+ with `pdo_pgsql`/`pgsql` (and,
for the concurrency test, `pcntl`/`posix`) plus a reachable Postgres, you
can skip Docker and run `composer test`/`lint`/`analyse` directly — set
`DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`/
`DB_OWNER_USERNAME`/`DB_OWNER_PASSWORD` to match (see `tests/TestCase.php`
for defaults, and `docker/postgres/init/01-create-app-role.sql` for how
the restricted test role is provisioned).

## Roadmap

This package is being built across four sessions of a documented,
session-based workflow, the same discipline used elsewhere in this
portfolio:

- **Session 1: governance and skeleton.** Composer package structure,
  Testbench-backed test harness with a passing placeholder test, CI
  (lint, static analysis, tests, Laravel version matrix, secret
  scanning, dependency vulnerability scanning), and the governance files
  a real open-source package needs before its first line of feature
  code.
- **Session 2 (this one): audit-log extraction.** privacy-forge's
  tamper-evident, hash-chained audit log, generalized into an
  `AuditLogger` service and `HasTamperEvidentAuditLog` trait any
  Eloquent model can use, plus the privilege-separation feature
  (`consent-guard:secure-audit-log`) that makes "no UPDATE/DELETE"
  something Postgres itself enforces.
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
