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
