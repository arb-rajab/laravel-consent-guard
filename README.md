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

This repository now ships two real features:

- A **tamper-evident, hash-chained audit log**, generalized out of
  privacy-forge's application-specific implementation into something any
  Eloquent model in any Laravel app can use (see
  [`docs/adr/0001-audit-log-tamper-evidence.md`](docs/adr/0001-audit-log-tamper-evidence.md)).
- **Consent guard**: a `ConsentRequired` cast, `EnsureConsentGranted`
  middleware, and a retention-sweep Artisan command that gate a field or
  action behind recorded, non-withdrawn consent — genuinely new design
  for this package (not extracted from privacy-forge), inspired only by
  privacy-forge's fail-closed *principle* for its policy evaluator, not
  its code (see [`docs/adr/0002-consent-guard-fail-closed.md`](docs/adr/0002-consent-guard-fail-closed.md)).

See Roadmap below for what's still to come.

## Requirements

- PHP 8.2+
- Laravel 12.x or 13.x (via `illuminate/*` components)
- **PostgreSQL, for the audit-log feature specifically** — it relies on
  `pg_advisory_xact_lock` for concurrency safety and on real role-level
  `GRANT`/`REVOKE` for privilege separation, neither of which has a
  database-agnostic equivalent this package could fall back to. Stated
  plainly rather than glossed over: that one feature does not work on
  MySQL/SQLite. Consent guard has no such requirement — it's plain
  Eloquent CRUD and works on any database Laravel supports.

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

## Consent guard

Mark your own user (or any other) model as a consent subject:

```php
use ArbRajab\ConsentGuard\Consent\Concerns\HasConsent;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentSubject;

class User extends Authenticatable implements ConsentSubject
{
    use HasConsent;
}
```

Grant, withdraw, and check consent for any purpose your own app defines —
`"marketing_email"` below is just an example string, not something this
package attaches special meaning to:

```php
$user->grantConsent('marketing_email');
$user->hasConsent('marketing_email'); // true
$user->withdrawConsent('marketing_email');
$user->hasConsent('marketing_email'); // false
```

Gate an Eloquent attribute behind a purpose — both reading and writing it
require valid, non-withdrawn consent, or a `ConsentRequiredException` is
thrown:

```php
use ArbRajab\ConsentGuard\Consent\Casts\ConsentRequired;

class User extends Authenticatable implements ConsentSubject
{
    use HasConsent;

    protected $casts = [
        'ssn' => ConsentRequired::class.':background_check',
    ];
}
```

Gate a route behind a purpose — denies with a `403` ahead of the
controller if the authenticated user hasn't implemented `ConsentSubject`
or doesn't have valid consent for it:

```php
Route::middleware('consent-guard:marketing_email')->post('/newsletter/subscribe', ...);
```

Both the cast and the middleware are **fail-closed**: if consent status
can't be determined at all (a lookup failure, an undeterminable subject),
access is denied, never silently allowed — see
[`docs/adr/0002-consent-guard-fail-closed.md`](docs/adr/0002-consent-guard-fail-closed.md).

Sweep consent records whose withdrawal/expiry is past its grace period —
dispatches `ConsentGracePeriodElapsed` for your own app to act on (this
package has no idea what your gated fields/rows actually are, so it
can't delete or anonymize them for you):

```bash
php artisan consent-guard:sweep-expired-consent          # dispatches + optionally purges
php artisan consent-guard:sweep-expired-consent --dry-run # reports only
```

```php
use ArbRajab\ConsentGuard\Consent\Events\ConsentGracePeriodElapsed;

Event::listen(function (ConsentGracePeriodElapsed $event) {
    // e.g. anonymize or delete whatever your app gated on
    // ($event->subjectType, $event->subjectId, $event->purpose)
});
```

### Installing it

```bash
php artisan vendor:publish --tag=consent-guard-config
php artisan vendor:publish --tag=consent-guard-migrations
php artisan migrate
```

Unlike the audit log, there is no privilege-separation step — consent
records need ordinary full CRUD from the application's own runtime role,
and nothing here is Postgres-specific.

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
- **Session 2: audit-log extraction.** privacy-forge's tamper-evident,
  hash-chained audit log, generalized into an `AuditLogger` service and
  `HasTamperEvidentAuditLog` trait any Eloquent model can use, plus the
  privilege-separation feature (`consent-guard:secure-audit-log`) that
  makes "no UPDATE/DELETE" something Postgres itself enforces.
- **Session 3 (this one): consent guard.** New package design (not an
  extraction): a `ConsentRequired` cast and `HasConsent`/`ConsentSubject`
  pairing that gate an Eloquent attribute behind a named consent purpose,
  an `EnsureConsentGranted` middleware that gates a route the same way,
  and `consent-guard:sweep-expired-consent` for retention. Fail-closed by
  principle (inspired by, but not sharing code with, privacy-forge's
  policy-evaluator ADR).
- **Session 4: Packagist publishing and upgrade documentation.**
  Tagged `v1.0.0`, published to Packagist, with an `UPGRADE.md` for the
  Laravel-major boundaries the CI matrix already covers.

## License

[MIT](LICENSE) — see [`SECURITY.md`](SECURITY.md) for vulnerability
reporting and [`CONTRIBUTING.md`](CONTRIBUTING.md) for the contribution
workflow.
