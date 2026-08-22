<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Tamper-Evident Audit Log
    |--------------------------------------------------------------------------
    |
    | This feature is PostgreSQL-specific: it relies on pg_advisory_xact_lock
    | to serialize concurrent hash-chain writes without needing the UPDATE
    | privilege (see AuditLogger), and on genuine role-level GRANT/REVOKE to
    | make the "no UPDATE/DELETE" guarantee something Postgres itself
    | enforces, not just something the application chooses not to expose.
    |
    */
    'audit_log' => [

        // The Laravel database connection the application uses at runtime
        // to read/write audit entries. Leave null to use the app's default
        // connection. This connection should authenticate as a Postgres
        // role that does NOT own the audit log table — see "owner_connection"
        // below and the consent-guard:secure-audit-log command.
        'connection' => env('CONSENT_GUARD_AUDIT_CONNECTION'),

        // A separate Laravel database connection, authenticated as a
        // Postgres role that DOES own the audit log table (typically the
        // role your migrations run as). Used only for two administrative
        // actions: creating the table, and running
        // `consent-guard:secure-audit-log` to lock it down. Never used by
        // the running application's normal request/queue-worker code path.
        //
        // A table owner can always GRANT privileges back to itself, so
        // "the app's own role revokes its own UPDATE/DELETE" is not a real
        // protection — it must be a genuinely different, non-owning role.
        'owner_connection' => env('CONSENT_GUARD_AUDIT_OWNER_CONNECTION'),

        // Table name for audit log entries.
        'table' => env('CONSENT_GUARD_AUDIT_TABLE', 'audit_log_entries'),

        // Fixed, global seed for pg_advisory_xact_lock(hashtext(...)).
        // Deliberately NOT partitioned by actor/subject/anything else: the
        // whole chain is one sequential log, so every writer must
        // contend for the same lock to keep prev_hash/entry_hash from
        // forking under concurrency.
        'lock_key' => env('CONSENT_GUARD_AUDIT_LOCK_KEY', 'consent_guard:audit_log_chain'),
    ],

];
