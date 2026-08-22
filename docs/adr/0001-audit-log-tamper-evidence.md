# ADR-0001 — Tamper-Evident Audit Log Design

- **Date:** 2026-08-22
- **Status:** accepted

## Context

This package generalizes a mechanism first built inside
[privacy-forge](https://github.com/arb-rajab/privacy-forge) (see that
repository's `docs/adr/ADR-0003-audit-log-tamper-evidence.md` and its `R-01`
risk-register entry), extracted so any Eloquent-based Laravel application —
with or without any compliance/consent requirement of its own — can adopt
tamper-evident audit logging via `composer require`.

Two independent properties are needed for an audit log's "nothing was
edited after the fact" claim to be meaningful rather than merely asserted:

1. **Detecting** that an entry was edited after being written.
2. **Preventing** the application's own (possibly compromised, possibly
   buggy) database credential from editing or deleting entries at all.

## Decision

**Hash-chained entries, enforced by an advisory lock, layered with
genuine database-level privilege separation.**

- Each `AuditLogEntry` stores `entry_hash = sha256(prev_entry_hash +
  json_encode(this_entry's_fields))`. `AuditLogger::verifyChain()` replays
  every entry in write order and confirms every stored hash matches its
  recomputed value — a single edited entry is then detectable because its
  `entry_hash` no longer matches what its (unedited) content recomputes to.
- Concurrent writers are serialized with a single, fixed, global
  `pg_advisory_xact_lock(hashtext(...))` key — deliberately **not**
  partitioned by actor, subject, or anything else, since the entire audit
  trail is one sequential chain and every writer must contend for the same
  lock to keep `prev_hash`/`entry_hash` from forking under concurrency.
- `consent-guard:secure-audit-log` revokes `UPDATE`/`DELETE` on the audit
  log table from the application's runtime role, at the Postgres level,
  via a **genuinely separate, non-owning role** — not the app's own role
  revoking its own privileges.

## Why not a self-revoke ("the app's own role just revokes its own UPDATE/DELETE")

Tested directly (documented in privacy-forge's decision log, Session 27):
a table owner **can** run `REVOKE UPDATE, DELETE ON t FROM owner_role`,
and Postgres genuinely enforces it against subsequent statements — but the
same owner role can just as trivially `GRANT` the privilege back to
itself afterward. Ownership carries the right to alter a table's ACL
regardless of the ACL's current contents, and that specific right cannot
itself be revoked short of `ALTER TABLE ... OWNER TO`. Against the actual
threat model here — the application's own runtime credential running
attacker-controlled or buggy arbitrary SQL — a self-revoke is only a soft
barrier: the same SQL access that could tamper with a row could just as
easily re-grant itself the privilege first. A role that never owned the
table and holds no grant option cannot do this: `GRANT` requires
ownership, superuser, or an existing grant option, none of which it has.

This is why `consent-guard:secure-audit-log` refuses to run when its
owner connection is authenticated as the same role it's asked to
restrict (see the command's own guard clause) — running it that way
would silently produce the same false sense of security.

## Why `pg_advisory_xact_lock` instead of `SELECT ... FOR UPDATE`

Postgres requires the `UPDATE` privilege for `SELECT ... FOR UPDATE`
**and** `SELECT ... FOR SHARE`, even though neither issues an actual
`UPDATE`. A role restricted to `SELECT`/`INSERT` only — the entire point
of this package's privilege-separation feature — could not take a row
lock on the audit table at all. An advisory lock needs no table privilege
of any kind and still serializes "read the last hash, compute the next
one, insert" the same way a row lock would.

## Generalization decisions specific to this package

privacy-forge's mechanism (hash-chaining, the advisory lock, privilege
separation) transfers unchanged — that's the point of extracting it. What
doesn't transfer unchanged is its shape: privacy-forge built this as one
application's internal service against its own schema, with no concept of
an unknown downstream consumer. Adapting it into something installed by
`composer require` into applications this package has never seen required
decisions privacy-forge never had to make:

- **Generic `actor_type`/`actor_id`/`subject_type`/`subject_id`/
  `metadata` instead of privacy-forge's `actor_user_id` (FK'd to its own
  `User` model)/`resource_type`/`resource_id`/`policy_id`/`decision`/
  `reason_code`.** privacy-forge's fields encode its own domain (a
  `policy_id` and `decision` only make sense next to a consent/DSAR
  engine); this package has no schema to join against in an application
  it doesn't control, so the actor and subject are opaque strings, not
  foreign keys, and `policy_id`/`decision`/`reason_code` collapse into
  a single arbitrary `metadata` payload the caller shapes however their
  own domain needs. This is *why* `metadata` exists as a `json` column
  at all — privacy-forge never needed one.
- **A service *and* a trait, not just a service.** privacy-forge only
  ever needed `app(AuditLogger::class)->record(...)` called from its own
  controllers. A package whose only entry point is a bare service class
  asks every consumer to wire up the same "pass my model's class and
  key as subjectType/subjectId" boilerplate by hand. `HasTamperEvidentAuditLog`
  exists purely to remove that boilerplate for the common "this model did
  something" case; `AuditLogger` stays available directly for entries
  that aren't about a specific model (e.g. a batch job). Both produce
  identical entries — the trait adds no behavior of its own.
- **Privilege separation shipped as a console command
  (`consent-guard:secure-audit-log`), not a migration.** privacy-forge's
  equivalent (`add_restricted_runtime_role_for_audit_log`) was a
  migration because it also *created* that application's runtime role —
  a one-time bootstrap step for a schema privacy-forge already owned. A
  package installed into an arbitrary application needs to accept which
  role to restrict and which connection owns the table as explicit
  input, and needs to refuse clearly when misconfigured (see the "why
  not a self-revoke" section above) — a migration can't take options or
  produce that kind of guided failure the way a command can.
- **No creation of the application's runtime database role.**
  privacy-forge's migration created `privacy_forge_app` from scratch,
  because at that point in privacy-forge's history it didn't yet exist.
  This package assumes its consumer already has a working runtime role
  (they're already running a Laravel app against Postgres) — this
  package's job starts at "narrow this existing role's privileges on one
  table," not "provision your database roles for you." Prescribing role
  creation/credential management for an application this package has
  never seen would be scope creep past what a generic library should own.

## What this package deliberately does NOT do

- **No external anchoring of the chain root.** privacy-forge's ADR-0003
  layers hash-chaining with periodic anchoring to storage outside the
  database, closing the gap where an attacker edits an entry *and*
  recomputes every subsequent hash to keep `verifyChain()` passing. That
  closes a real gap, but *where* to anchor (S3, a signed release, a
  separate append-only log) is inherently specific to a host
  application's own infrastructure, not something a generic package can
  prescribe. `verifyChain()` here proves tamper **evidence** against
  single-entry edits; it does not, on its own, defend against a
  full-chain rewrite. A consuming application that needs that stronger
  guarantee should anchor `AuditLogEntry::orderByDesc('sequence')->
  first()->entry_hash` externally on its own schedule.
- **No creation of the application's runtime database role.** A host
  application is assumed to already have (or be willing to create) the
  Postgres role it connects as. This package's job starts at "narrow an
  existing role's privileges on one table it doesn't need full CRUD on,"
  not "provision your database roles for you."
- **Postgres only.** The hash-chain mechanism itself is
  database-agnostic, but the concurrency-safety and privilege-separation
  mechanisms are both genuinely Postgres-specific (`pg_advisory_xact_lock`,
  role-level `GRANT`/`REVOKE` semantics). Stated honestly rather than
  implying broader database support that doesn't exist yet.

## Consequences

- Adopting this feature requires PostgreSQL and a second, non-owning
  database role — a real operational step, not a config toggle. This is
  the intended trade: the whole point of the feature is a guarantee that
  costs something to set up honestly, rather than a guarantee that's
  actually just the application trusting itself.
- `AuditLogEntry`'s `metadata` column is a plain `json` column (not
  `jsonb`) specifically because Postgres's `json` type preserves input
  text verbatim (including key order), which hash recomputation on read
  depends on. `jsonb` does not make that guarantee.
