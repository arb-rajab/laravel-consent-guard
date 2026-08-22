<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests\Feature\AuditLog;

/**
 * Test-only schema setup/teardown for the audit log. Deliberately bypasses
 * Laravel's `migrations` tracking table: each test gets a fresh table via
 * a direct up()/down() call on the migration file, run against the
 * pgsql_owner connection (so the owner role — not the restricted app role
 * — owns the table, matching real deployment). Using `php artisan migrate`
 * here would leave a stale tracking row after dropAuditLogTable() drops
 * the table itself, causing the next test's migration to be silently
 * skipped as "already run".
 */
trait InteractsWithAuditLogSchema
{
    protected function migrateAuditLogTable(): void
    {
        $this->withOwnerConnectionAsDefault(
            fn () => (require __DIR__.'/../../../database/migrations/2026_01_01_000000_create_audit_log_entries_table.php')->up()
        );
    }

    protected function dropAuditLogTable(): void
    {
        $this->withOwnerConnectionAsDefault(
            fn () => (require __DIR__.'/../../../database/migrations/2026_01_01_000000_create_audit_log_entries_table.php')->down()
        );
    }

    protected function secureAuditLogTable(): void
    {
        $exitCode = $this->artisan('consent-guard:secure-audit-log', [
            '--owner-connection' => 'pgsql_owner',
        ])->run();

        if ($exitCode !== 0) {
            throw new \RuntimeException('consent-guard:secure-audit-log failed with exit code '.$exitCode);
        }
    }

    private function withOwnerConnectionAsDefault(callable $callback): void
    {
        $previousDefault = config('database.default');
        config(['database.default' => 'pgsql_owner']);

        try {
            $callback();
        } finally {
            config(['database.default' => $previousDefault]);
        }
    }
}
