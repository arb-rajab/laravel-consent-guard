<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\AuditLog\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Proves the DB-grant half of the tamper-evidence design is real.
// AuditLogEntry::save()/delete() already throw at the application layer —
// that alone only proves this package's own Eloquent calls are blocked,
// not that the database itself would refuse. Every statement below is raw
// SQL issued through the connection an application actually runs on
// (config('consent-guard.audit_log.connection'), the same "pgsql"
// connection AuditLogger itself uses) — no test-only elevated credential —
// specifically to rule out "the app just chooses not to expose this" as
// the reason it fails.
beforeEach(function () {
    $this->migrateAuditLogTable();
    $this->secureAuditLogTable();
});

afterEach(function () {
    $this->dropAuditLogTable();
});

it('connects to the database as the restricted app role, not the table owner', function () {
    $appRole = DB::connection('pgsql')->selectOne('SELECT current_user AS role')->role;
    $ownerRole = DB::connection('pgsql_owner')->selectOne('SELECT current_user AS role')->role;

    expect($appRole)
        ->toBe(config('database.connections.pgsql.username'))
        ->not->toBe($ownerRole);
});

it('rejects a raw SQL UPDATE against the audit log table at the Postgres level', function () {
    $entry = app(AuditLogger::class)->record('system', null, 'test.privsep.update-attempt', 'test_resource', 'r-1');
    $table = (string) config('consent-guard.audit_log.table');

    $threw = false;

    try {
        // Wrapped in its own transaction so the permission error doesn't
        // poison a wider transaction and mask the verification query below
        // behind "current transaction is aborted".
        DB::connection('pgsql')->transaction(function () use ($entry, $table) {
            DB::connection('pgsql')->statement("UPDATE {$table} SET action = ? WHERE id = ?", ['tampered', $entry->id]);
        });
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getCode())->toBe('42501'); // insufficient_privilege
        expect($e->getMessage())->toContain('permission denied');
    }

    expect($threw)->toBeTrue('Expected Postgres to reject the UPDATE with a permission error; the app role must not have UPDATE on the audit log table.');
    expect(DB::connection('pgsql_owner')->table($table)->where('id', $entry->id)->value('action'))
        ->toBe('test.privsep.update-attempt');
});

it('rejects a raw SQL DELETE against the audit log table at the Postgres level', function () {
    $entry = app(AuditLogger::class)->record('system', null, 'test.privsep.delete-attempt', 'test_resource', 'r-1');
    $table = (string) config('consent-guard.audit_log.table');

    $threw = false;

    try {
        DB::connection('pgsql')->transaction(function () use ($entry, $table) {
            DB::connection('pgsql')->statement("DELETE FROM {$table} WHERE id = ?", [$entry->id]);
        });
    } catch (QueryException $e) {
        $threw = true;
        expect($e->getCode())->toBe('42501');
        expect($e->getMessage())->toContain('permission denied');
    }

    expect($threw)->toBeTrue('Expected Postgres to reject the DELETE with a permission error; the app role must not have DELETE on the audit log table.');
    expect(DB::connection('pgsql_owner')->table($table)->where('id', $entry->id)->exists())->toBeTrue();
});

it('still allows SELECT and INSERT against the audit log table for the app role', function () {
    // Positive control: privilege separation must narrow the grant, not
    // accidentally remove the privileges the app genuinely needs.
    $entry = app(AuditLogger::class)->record('system', null, 'test.privsep.positive-control', 'test_resource', 'r-1');
    $table = (string) config('consent-guard.audit_log.table');

    expect(DB::connection('pgsql')->table($table)->where('id', $entry->id)->exists())->toBeTrue();
});
