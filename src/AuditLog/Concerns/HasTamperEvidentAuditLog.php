<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\AuditLog\Concerns;

use ArbRajab\ConsentGuard\AuditLog\AuditLogEntry;
use ArbRajab\ConsentGuard\AuditLog\AuditLogger;

/**
 * Convenience wrapper for any Eloquent model: records an audit entry with
 * this model as the subject, delegating to AuditLogger for the actual
 * hash-chained write. Purely a naming/ergonomics layer — $model->
 * recordAuditEntry(...) and app(AuditLogger::class)->record(...) produce
 * identical entries.
 */
trait HasTamperEvidentAuditLog
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function recordAuditEntry(
        string $action,
        array $metadata = [],
        string $actorType = 'system',
        string|int|null $actorId = null,
    ): AuditLogEntry {
        return app(AuditLogger::class)->record(
            actorType: $actorType,
            actorId: $actorId,
            action: $action,
            subjectType: static::class,
            subjectId: $this->getKey(),
            metadata: $metadata,
        );
    }
}
