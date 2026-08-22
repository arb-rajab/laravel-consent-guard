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

    /*
    |--------------------------------------------------------------------------
    | Consent Guard
    |--------------------------------------------------------------------------
    |
    | Unlike the audit log above, nothing here is Postgres-specific — it's
    | plain Eloquent reads/writes, so it works on any database Laravel
    | supports. "Purpose" is an arbitrary string your own application
    | defines (e.g. "marketing_email", "background_check") — this package
    | attaches no compliance-specific meaning to it.
    |
    */
    'consent' => [

        // The database connection consent records are read/written on.
        // Leave null to use the app's default connection.
        'connection' => env('CONSENT_GUARD_CONSENT_CONNECTION'),

        // Table name for consent records.
        'table' => env('CONSENT_GUARD_CONSENT_TABLE', 'consent_records'),

        // Fallback grace period (in days) applied after a consent record's
        // withdrawn_at or expires_at before
        // `consent-guard:sweep-expired-consent` treats it as swept.
        'default_grace_period_days' => (int) env('CONSENT_GUARD_DEFAULT_GRACE_PERIOD_DAYS', 30),

        // Register your own purposes here. Nothing about a purpose's
        // meaning is known to, or needed by, this package's internals —
        // "grace_period_days" (falls back to default_grace_period_days
        // above when omitted) is the only key the sweep command reads.
        // Add whatever else your own application finds useful (a label,
        // a description) without editing any package internals.
        'purposes' => [
            // 'marketing_email' => ['grace_period_days' => 14],
        ],

        // Whether consent-guard:sweep-expired-consent deletes the
        // ConsentRecord row itself after dispatching
        // ConsentGracePeriodElapsed for it. Default false: the package
        // only reports/dispatches by default and leaves deletion — of the
        // consent record and/or the gated data it was about — to the host
        // application's own event listener, which alone knows what
        // "act on it" should mean for its own schema.
        'purge_expired_records' => (bool) env('CONSENT_GUARD_PURGE_EXPIRED_RECORDS', false),

        // HTTP response returned by the EnsureConsentGranted middleware
        // when consent is missing, withdrawn, expired, or undeterminable.
        'deny_status' => (int) env('CONSENT_GUARD_DENY_STATUS', 403),
        'deny_message' => env('CONSENT_GUARD_DENY_MESSAGE', 'This action requires valid consent.'),
    ],

];
