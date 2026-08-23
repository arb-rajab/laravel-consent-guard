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

## What's extracted from privacy-forge, and what's newly designed

Stated plainly, feature by feature, rather than left to be inferred from ADR
cross-references:

- **Audit log — extracted and generalized.** The hash-chain mechanism
  itself (`entry_hash = sha256(prev_hash + fields)`, `pg_advisory_xact_lock`
  for concurrency safety, real Postgres `GRANT`/`REVOKE` for privilege
  separation) is privacy-forge's `App\Services\AuditLogger`
  (that repo's ADR-0003, risk entry R-01), carried over largely as-is
  because it's genuine Postgres mechanics, not application logic. What
  changed in the extraction: domain-specific fields (`policy_id`,
  `decision`, `reason_code`, a `User`-typed actor) were replaced with
  generic ones (`actor_type`/`actor_id`, `subject_type`/`subject_id`, an
  arbitrary `metadata` array) so the mechanism makes sense for an app with
  no GDPR concerns at all; the privilege-separation step became an
  installable Artisan command instead of a one-off migration; and the
  package deliberately does not create the host app's runtime database
  role (privacy-forge's own migration did, since it was bootstrapping a
  brand-new split within one application it fully owned). See
  [`docs/adr/0001-audit-log-tamper-evidence.md`](docs/adr/0001-audit-log-tamper-evidence.md)
  for the full reasoning.
- **Consent guard — new design, not an extraction.** No privacy-forge code
  was reused for this feature at all. What's borrowed is a *principle*:
  privacy-forge's `docs/adr/ADR-0006-policy-evaluator-fail-closed.md` was
  read (read-only) for its fail-closed reasoning before this feature's own
  `ConsentManager::isGranted()` was designed as this package's single
  fail-closed choke point — privacy-forge's `PolicyEvaluator` code was
  never imported, depended on, or even copied from. The data model
  (current-state `ConsentRecord`, one row per subject+purpose), the
  cast/middleware/trait API surface, and the retention-sweep command are
  all designed from scratch for this package. See
  [`docs/adr/0002-consent-guard-fail-closed.md`](docs/adr/0002-consent-guard-fail-closed.md).

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

> **Not yet on Packagist** — see "Packagist publishing" below. Until then,
> require it via a [path or VCS repository](https://getcomposer.org/doc/05-repositories.md#path)
> pointing at this repository; the command below is what it becomes once
> published.

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

## Proven end-to-end, in a real separate application

Every claim above has been verified against real infrastructure, not
reasoned about — including, as of this package's `v1.0.0` release, a real
integration proof that goes beyond this repository's own Testbench-based
test suite:

- A fresh `laravel/laravel` (13.26.1) application was built from scratch
  and this package installed into it with a genuine
  `composer require arb-rajab/laravel-consent-guard` (via a local `path`
  repository standing in for Packagist, since this package isn't published
  there yet — see "Packagist publishing" below), not required as a
  Testbench dev dependency.
- Both features were driven through **real HTTP requests** against that
  separate application's own `php artisan serve` process: a
  session-authenticated user hitting a `consent-guard`-gated route (403
  before consent, 200 after granting it, 403 again after withdrawing it —
  proving the check is live, not cached), a `ConsentRequired`-cast field
  correctly isolating one purpose's consent from another's, and an
  `AuditLogger`-backed endpoint recording real hash-chained entries.
- The privilege-separation guarantee was verified against **that
  application's own live database**, not the test sandbox: connecting
  directly as `consent_guard_app` (the exact role the running app uses)
  and confirming Postgres itself rejects `UPDATE`/`DELETE` on the audit
  log table with `42501 permission denied`, while `AuditLogger::verifyChain()`
  correctly reports `valid: true` normally and `valid: false` (with the
  exact broken sequence number) after the table-owning role tampers with
  one row directly.

See `HANDOFF.md`'s Session 4 entry for the full transcript of this proof.

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
- **Session 3: consent guard.** New package design (not an
  extraction): a `ConsentRequired` cast and `HasConsent`/`ConsentSubject`
  pairing that gate an Eloquent attribute behind a named consent purpose,
  an `EnsureConsentGranted` middleware that gates a route the same way,
  and `consent-guard:sweep-expired-consent` for retention. Fail-closed by
  principle (inspired by, but not sharing code with, privacy-forge's
  policy-evaluator ADR).
- **Session 4 (this one): release readiness.** A real integration proof
  in a separate throwaway Laravel application (see "Proven end-to-end"
  above), the version-compatibility matrix re-confirmed against real CI
  logs, this README finished for a real stranger to follow, `v1.0.0`
  tagged, and `UPGRADE.md` added. **Packagist publishing itself is a
  deferred, human-only step** — see below.

## Packagist publishing

This package is not yet published to Packagist. Doing so requires a real
Packagist account and submitting the repository there, which is not
something that can be done from this environment (no such credentials
exist here) — the same situation as the cloud-provisioning step in
privacy-forge: a human step, stated plainly rather than faked. To publish
`v1.0.0` once tagged:

1. Create (or sign in to) a [Packagist](https://packagist.org) account.
2. Submit `https://github.com/arb-rajab/laravel-consent-guard` as a new
   package. Packagist reads `composer.json` directly from the repository,
   so no separate metadata upload is needed.
3. On the package's Packagist settings page, add the GitHub webhook (or
   use Packagist's own "GitHub Hook" one-click setup, which requires
   granting Packagist access to the GitHub account/org) so new tags are
   picked up automatically — without it, updates require manually clicking
   "Update" on Packagist after every future release.
4. Confirm `composer require arb-rajab/laravel-consent-guard` resolves the
   package from a machine that has never had the `path` repository
   override this README's own integration proof used.

## License

[MIT](LICENSE) — see [`SECURITY.md`](SECURITY.md) for vulnerability
reporting and [`CONTRIBUTING.md`](CONTRIBUTING.md) for the contribution
workflow.
