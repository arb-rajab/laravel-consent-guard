<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Current-state consent record for one (subject, purpose) pair — one row
 * per pair (see the migration's unique index), updated in place by
 * ConsentManager::grant()/withdraw() rather than appended to. This is
 * deliberately a different shape from AuditLog\AuditLogEntry: consent
 * status needs "what is true right now," not a tamper-evident history of
 * every change. An adopting app that also wants a history of consent
 * changes can layer AuditLog\Concerns\HasTamperEvidentAuditLog on top of
 * ConsentManager's own call sites — this package does not couple the two
 * features together.
 *
 * @property string $id
 * @property string $subject_type
 * @property string $subject_id
 * @property string $purpose
 * @property Carbon|null $granted_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ConsentRecord extends Model
{
    use HasUuids;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'purpose',
        'granted_at',
        'withdrawn_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return (string) config('consent-guard.consent.table', 'consent_records');
    }

    public function getConnectionName(): ?string
    {
        return config('consent-guard.consent.connection');
    }

    /**
     * Granted, not withdrawn, and not past its own expiry — the single
     * definition of "valid consent" every reader (ConsentManager, the
     * ConsentRequired cast, EnsureConsentGranted) must agree on.
     */
    public function isGranted(): bool
    {
        if ($this->granted_at === null || $this->withdrawn_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
