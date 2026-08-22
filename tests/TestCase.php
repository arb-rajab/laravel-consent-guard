<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests;

use ArbRajab\ConsentGuard\ConsentGuardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ConsentGuardServiceProvider::class];
    }

    /**
     * The audit-log feature is Postgres-specific (pg_advisory_xact_lock,
     * role-level GRANT/REVOKE), so tests that exercise it need two real
     * Postgres connections: one authenticated as a non-owning "app"
     * role (what AuditLogger uses) and one as the role that owns the
     * audit log table (used only to migrate and to run
     * consent-guard:secure-audit-log). See docker-compose.yml and
     * .github/workflows/ci.yml for how both roles are provisioned.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'pgsql');

        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'consent_guard_test'),
            'username' => env('DB_USERNAME', 'consent_guard_app'),
            'password' => env('DB_PASSWORD', 'consent_guard_app_password'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        $app['config']->set('database.connections.pgsql_owner', [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'consent_guard_test'),
            'username' => env('DB_OWNER_USERNAME', 'postgres'),
            'password' => env('DB_OWNER_PASSWORD', 'postgres'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        $app['config']->set('consent-guard.audit_log.connection', 'pgsql');
        $app['config']->set('consent-guard.audit_log.owner_connection', 'pgsql_owner');
    }
}
