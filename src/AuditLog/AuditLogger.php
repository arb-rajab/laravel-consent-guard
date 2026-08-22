<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\AuditLog;

use Illuminate\Support\Facades\DB;

/**
 * Hash-chained, tamper-evident audit log. Any Eloquent app can call
 * record() directly, or use the HasTamperEvidentAuditLog trait for a
 * per-model convenience wrapper. This is the *only* supported way to
 * create an AuditLogEntry — it's what computes prev_hash/entry_hash
 * correctly; AuditLogEntry itself refuses to be updated or deleted.
 *
 * Concurrency safety: record() serializes writers with
 * pg_advisory_xact_lock rather than SELECT ... FOR UPDATE, because
 * Postgres requires the UPDATE privilege for FOR UPDATE/FOR SHARE locks
 * even though neither issues an actual UPDATE. A role restricted to
 * SELECT/INSERT only (see consent-guard:secure-audit-log) could not take
 * a row lock at all — an advisory lock needs no table privilege and still
 * serializes "read the last hash, compute the next one, insert".
 *
 * This mechanism proves tamper *evidence* (any single-entry edit breaks
 * verifyChain()), not tamper *impossibility*: an attacker able to edit
 * an entry and recompute every subsequent hash could still make a
 * doctored chain internally consistent again. Closing that gap requires
 * anchoring the chain root somewhere outside this database entirely —
 * deliberately left out of this package, which ships the generic
 * mechanism, not a specific anchoring destination.
 */
class AuditLogger
{
    public const GENESIS_HASH_CHAR = '0';

    public static function genesisHash(): string
    {
        return str_repeat(self::GENESIS_HASH_CHAR, 64);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $actorType,
        string|int|null $actorId,
        string $action,
        string $subjectType,
        string|int $subjectId,
        array $metadata = [],
    ): AuditLogEntry {
        $connection = config('consent-guard.audit_log.connection');

        return DB::connection($connection)->transaction(function () use ($connection, $actorType, $actorId, $action, $subjectType, $subjectId, $metadata) {
            DB::connection($connection)->statement('SELECT pg_advisory_xact_lock(hashtext(?))', [$this->lockKeySeed()]);

            $previous = AuditLogEntry::query()->orderByDesc('sequence')->first();
            $prevHash = $previous === null ? self::genesisHash() : $previous->entry_hash;

            $payload = [
                'actor_type' => $actorType,
                'actor_id' => $actorId === null ? null : (string) $actorId,
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => (string) $subjectId,
                'metadata' => $metadata,
            ];

            $entryHash = self::computeHash($prevHash, $payload);

            $entry = AuditLogEntry::create([
                ...$payload,
                'prev_hash' => $prevHash,
                'entry_hash' => $entryHash,
                'created_at' => now(),
            ]);

            // `sequence` is a database-generated default (nextval(...)), not
            // something Eloquent's insert reports back the way it does for
            // an autoincrementing primary key — without this, the returned
            // instance's sequence attribute is null until separately queried.
            return $entry->refresh();
        });
    }

    /**
     * Replay the entire chain in write order and confirm every stored hash
     * matches its recomputed value.
     *
     * @return array{valid: bool, brokenAtSequence: int|null}
     */
    public function verifyChain(): array
    {
        $expectedPrevHash = self::genesisHash();

        foreach (AuditLogEntry::query()->orderBy('sequence')->cursor() as $entry) {
            if ($entry->prev_hash !== $expectedPrevHash) {
                return ['valid' => false, 'brokenAtSequence' => $entry->sequence];
            }

            $payload = [
                'actor_type' => $entry->actor_type,
                'actor_id' => $entry->actor_id,
                'action' => $entry->action,
                'subject_type' => $entry->subject_type,
                'subject_id' => $entry->subject_id,
                'metadata' => $entry->metadata ?? [],
            ];

            if (self::computeHash($entry->prev_hash, $payload) !== $entry->entry_hash) {
                return ['valid' => false, 'brokenAtSequence' => $entry->sequence];
            }

            $expectedPrevHash = $entry->entry_hash;
        }

        return ['valid' => true, 'brokenAtSequence' => null];
    }

    private function lockKeySeed(): string
    {
        return (string) config('consent-guard.audit_log.lock_key', 'consent_guard:audit_log_chain');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function computeHash(string $prevHash, array $payload): string
    {
        return hash('sha256', $prevHash.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
