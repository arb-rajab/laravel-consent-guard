# ADR-0002 — Consent Guard: Fail-Closed by Design

- **Date:** 2026-08-22
- **Status:** accepted

## Context

Session 2 shipped the tamper-evident audit log. This session adds the
second, independent feature: consent-gating for fields and actions —
a `ConsentRequired` Eloquent cast, an `EnsureConsentGranted` HTTP
middleware, a `consent-guard:sweep-expired-consent` Artisan command, and
the `ConsentManager`/`ConsentRecord`/`HasConsent` machinery underneath
them.

This is **new package design, not an extraction**. Unlike the audit log
(generalized line-by-line from privacy-forge's own `AuditLogger`), no
privacy-forge code is reused here. What *is* deliberately borrowed is a
**principle**: privacy-forge's `docs/adr/ADR-0006-policy-evaluator-fail-closed.md`
establishes that its `PolicyEvaluator` must default to "deny" whenever it
cannot reach a clear, unambiguous "allow" — a missing policy, a malformed
condition, an exception during evaluation, a database error while
fetching policy rows. That ADR's reasoning is read (read-only) as
inspiration for consent-guard's own fail-closed default; its
`PolicyEvaluator` code is not touched, imported, or depended on in any
way, and consent-guard's domain (an arbitrary "purpose" string an
adopting app defines) has nothing to do with privacy-forge's ABAC
policies, DSARs, or erasure.

## Decision

**Every ambiguous or error state resolves to "not granted," never to
"granted."** Concretely, in `ConsentManager::isGranted()` — the single
choke point every other part of this feature (the cast, the middleware)
calls through, never re-implementing this logic themselves:

- No consent record for the (subject, purpose) pair at all → not granted.
- A record exists but was withdrawn → not granted.
- A record exists, was granted, but has since expired → not granted.
- The lookup itself throws (a database outage, a corrupt connection,
  anything) → not granted. The exception is reported (`report($e)`) and
  swallowed at this boundary specifically so a caller can't accidentally
  let it propagate into a request that then falls through to whatever
  the framework's default exception handling would otherwise do (which,
  for many apps, is *not* "deny this specific action" — it's a generic
  500, or in the worst case a misconfigured handler that resumes
  execution past a place where the caller assumed the check had run).

This is proven, not just asserted: `ConsentFailClosedFaultInjectionTest`
grants real, valid consent for a subject *first*, then swaps
`ConsentManager`'s only dependency (`Contracts\ConsentRepository`) for a
fake that unconditionally throws, and confirms both that
`isGranted()` still returns `false` (not an uncaught exception) and that
an HTTP request through `EnsureConsentGranted` still gets a 403 (not a
200, and not an unhandled 500). Granting real consent first, then only
breaking the lookup, rules out "there was nothing to grant anyway" as an
explanation for the denial — the fault is what caused it.

### Why a swappable repository, not a broken real connection

The audit log's own fault-injection proof (Session 2) disabled a real
`pg_advisory_xact_lock` call against a real Postgres instance and
widened a race window to prove the concurrency guard has teeth. Consent
guard's fail-closed guarantee doesn't need — and, unlike a concurrency
race, can't cleanly be proven by — corrupting a real database mid-test;
the thing being proven is "what happens when the lookup throws," and a
`Contracts\ConsentRepository` implementation that deterministically
throws is a more precise, faster, and equally real way to inject that
exact fault than trying to sever a live connection at exactly the right
moment. `Contracts\ConsentRepository` exists in this package's public
API specifically to make this substitution possible for any adopting
app's own tests too, not just this package's.

### Failing closed on an undeterminable subject, not just on withdrawn consent

The `ConsentRequired` cast and `EnsureConsentGranted` middleware both also
deny when they cannot establish *who* consent should even be checked
for: a model that doesn't implement `Contracts\ConsentSubject`, or one
that implements it but has no determinable identity yet (an unsaved
model whose primary key hasn't been assigned). This is the same
principle applied one step earlier — "I don't know whose consent to
check" is exactly the kind of ambiguity ADR-0006 says must deny, not be
treated as "no check needed."

## Why this feature needs no privilege separation, unlike the audit log

The audit log's tamper-evidence guarantee specifically requires that the
application's own runtime role *cannot* rewrite history even if
compromised — hence a genuinely separate, non-owning Postgres role and
real `REVOKE UPDATE, DELETE`. Consent-guard's fail-closed guarantee is a
different kind of property: it's about what the *application code*
decides when it can't be sure, not about restricting what the
*database* will permit. An application's own runtime role legitimately
needs full `SELECT`/`INSERT`/`UPDATE`/`DELETE` on `consent_records` (to
grant, withdraw, and let the retention sweep purge) — there is no
analogous "must not be able to edit its own consent records" threat
model here. Nothing in this feature is Postgres-specific either: it's
plain Eloquent CRUD, so (unlike the audit log) it runs on any database
Laravel supports. This package's own tests run consent-guard's suite
against the same Postgres instance already provisioned for the audit
log purely for infra reuse, not because Postgres is required — see
`tests/Feature/Consent/InteractsWithConsentSchema.php` for the one
place this shared sandbox's two-role split leaked into test setup (a
real single-role Laravel app needs no such step, since its own
migrations already run as the same role it queries with at runtime).

## Why retention is an event, not a package-owned deletion

`consent-guard:sweep-expired-consent` finds `ConsentRecord` rows whose
withdrawal or expiry is past a configurable grace period and dispatches
`Events\ConsentGracePeriodElapsed` for each — it does not touch any of
the host application's own gated fields or rows directly. This package
has no schema to act on beyond its own `consent_records` table; only the
host application knows what "acting on" expired consent should mean for
its own domain (null a column, anonymize a row, delete a record
entirely, or something else). The command *can* optionally delete the
`ConsentRecord` row itself after dispatching
(`consent-guard.consent.purge_expired_records`), because that row is
this package's own, not the host's.

## What this package deliberately does NOT do

- **No GDPR/DSAR-specific naming anywhere in the public API.** "Purpose,"
  "subject," "grant," "withdraw" are the vocabulary of consent generally,
  not of any specific compliance regime — the same bar Session 2 held
  the audit log's `subject_type`/`actor_type`/`metadata` fields to. An
  app with a purely commercial "notify me about price drops" checkbox
  can use this exactly as naturally as a compliance-driven one.
- **No coupling to the audit log feature.** An adopting app that wants a
  tamper-evident history of consent grants/withdrawals can call
  `AuditLog\Concerns\HasTamperEvidentAuditLog::recordAuditEntry()` from
  its own `ConsentGracePeriodElapsed` listener (or wherever it calls
  `grantConsent()`/`withdrawConsent()`) — this package does not do that
  wiring itself, since not every consumer of one feature wants the other.
- **No enforced registry of valid purposes.** `config('consent-guard.consent.purposes')`
  is read only for its optional `grace_period_days` override; a purpose
  string not listed there still works everywhere else. Requiring
  upfront registration was considered and rejected: it would mean every
  new purpose needs a config *and* a code change, which is exactly the
  "editing package internals" friction the config file exists to avoid.

## Consequences

- A database outage or bug in the consent-lookup path degrades to
  "nothing consent-gated is accessible" rather than "everything
  consent-gated is quietly accessible" — the same availability-for-safety
  trade-off ADR-0006 accepted, inherited here as a matter of principle
  rather than shared code.
- Adopting this feature means the host app's own `ConsentSubject` model
  (typically its user model) must implement that interface — a small,
  explicit, one-line contract to satisfy, deliberately mirroring
  Laravel's own `MustVerifyEmail`/`CanVerifyEmail` pairing rather than
  inventing a new convention.
