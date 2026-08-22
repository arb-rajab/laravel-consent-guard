<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests\Feature\Consent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Test-only schema setup/teardown. Nothing about the consent-guard feature
 * itself needs an owner/app role split the way the audit log's privilege
 * separation does — a real single-role Laravel app runs its own
 * migrations as the same role it queries with at runtime, so that role
 * already owns (and has full CRUD on) every table it creates, with no
 * extra GRANT step required.
 *
 * This *test* environment happens to reuse the audit log's two-role
 * Postgres setup for infra convenience (see docker-compose.yml /
 * docker/postgres/init/01-create-app-role.sql) rather than provisioning a
 * second, separate single-role database — which means the restricted
 * "app" role (consent_guard_app, what the default `pgsql` connection
 * authenticates as) has no privileges at all on a table it doesn't own,
 * not even after creating it. Found for real, not assumed: the first run
 * of this suite failed every consent test with Postgres's own
 * "permission denied for schema public," because CREATE on schema public
 * was never granted to that role (correctly — the audit log deliberately
 * restricts it). So these tables are created via the owning connection,
 * same as the audit log's, and then explicitly GRANTed full CRUD to the
 * app role — a step a real single-role app would never need, since it
 * would already own the table.
 */
trait InteractsWithConsentSchema
{
    protected function migrateConsentTable(): void
    {
        $this->withOwnerConnectionAsDefault(function () {
            (require __DIR__.'/../../../database/migrations/2026_01_02_000000_create_consent_records_table.php')->up();

            $this->grantAppRoleFullAccess((string) config('consent-guard.consent.table', 'consent_records'));
        });
    }

    protected function dropConsentTable(): void
    {
        $this->withOwnerConnectionAsDefault(
            fn () => (require __DIR__.'/../../../database/migrations/2026_01_02_000000_create_consent_records_table.php')->down()
        );
    }

    /**
     * Schema for tests/Fixtures/Person.php — the fixture the
     * ConsentRequired cast tests exercise real Eloquent get/set through.
     */
    protected function migratePeopleTable(): void
    {
        $this->withOwnerConnectionAsDefault(function () {
            Schema::create('people', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('ssn')->nullable();
                $table->timestamps();
            });

            $this->grantAppRoleFullAccess('people');
        });
    }

    protected function dropPeopleTable(): void
    {
        $this->withOwnerConnectionAsDefault(fn () => Schema::dropIfExists('people'));
    }

    private function grantAppRoleFullAccess(string $table): void
    {
        $appRole = (string) config('database.connections.pgsql.username');

        DB::connection('pgsql_owner')->statement(
            sprintf('GRANT SELECT, INSERT, UPDATE, DELETE ON %s TO %s', $this->quoteIdent($table), $this->quoteIdent($appRole))
        );
    }

    private function quoteIdent(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
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
