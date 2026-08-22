<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `sequence` is a plain auto-incrementing column that gives the hash chain
 * a deterministic, gap-free write order — the uuid `id` is not itself
 * sortable, and Postgres doesn't guarantee same-millisecond timestamps are
 * distinct. `metadata` is deliberately a plain `json` column, not `jsonb`:
 * Postgres's `json` type stores the exact input text verbatim (including
 * key order), which AuditLogger's hash recomputation on read depends on —
 * `jsonb` does not make that guarantee.
 *
 * Run this migration via a connection authenticated as the Postgres role
 * that should OWN this table (see config/consent-guard.php's
 * `owner_connection` and the consent-guard:secure-audit-log command) —
 * e.g. `php artisan migrate --database=<owner-connection-name>`. Whichever
 * role runs this migration becomes the table owner, and only a non-owning
 * role's GRANT can be relied on to keep UPDATE/DELETE revoked.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('consent-guard.audit_log.table', 'audit_log_entries');

        Schema::create($table, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('sequence')->unique();
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('metadata')->nullable();
            $table->string('prev_hash')->nullable();
            $table->string('entry_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index('created_at');
        });

        $sequence = $table.'_sequence_seq';
        DB::statement("CREATE SEQUENCE IF NOT EXISTS {$sequence} OWNED BY {$table}.sequence");
        DB::statement("ALTER TABLE {$table} ALTER COLUMN sequence SET DEFAULT nextval('{$sequence}')");
    }

    public function down(): void
    {
        $table = (string) config('consent-guard.audit_log.table', 'audit_log_entries');
        $sequence = $table.'_sequence_seq';

        Schema::dropIfExists($table);
        DB::statement("DROP SEQUENCE IF EXISTS {$sequence}");
    }
};
