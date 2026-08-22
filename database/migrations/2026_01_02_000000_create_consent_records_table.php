<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unlike the audit log's migration, this one needs no owning/non-owning
 * role split — there is no privilege-separation feature for consent
 * records, and nothing here is Postgres-specific. Run it via whatever
 * connection an adopting app already runs its own migrations on.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = (string) config('consent-guard.consent.table', 'consent_records');
        $connection = config('consent-guard.consent.connection');

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject_type');
            $table->string('subject_id');
            $table->string('purpose');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id', 'purpose']);
            $table->index(['purpose', 'withdrawn_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        $table = (string) config('consent-guard.consent.table', 'consent_records');
        $connection = config('consent-guard.consent.connection');

        Schema::connection($connection)->dropIfExists($table);
    }
};
