<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Concerns;

use ArbRajab\ConsentGuard\Consent\ConsentManager;
use ArbRajab\ConsentGuard\Consent\ConsentRecord;
use DateTimeInterface;

/**
 * Convenience wrapper for a model that implements ConsentSubject: gives it
 * grantConsent()/withdrawConsent()/hasConsent() without the caller having
 * to spell out consentSubjectType()/consentSubjectId() at every call site.
 * Purely ergonomics, the same way AuditLog\Concerns\HasTamperEvidentAuditLog
 * is a thin wrapper over AuditLogger — this trait produces identical
 * ConsentRecord rows to calling ConsentManager directly.
 *
 * A class using this trait must also `implements ConsentSubject` (this
 * trait provides that interface's two methods, but a trait alone cannot
 * make `instanceof ConsentSubject` true) — the same pairing convention
 * Laravel's own MustVerifyEmail/CanVerifyEmail use.
 */
trait HasConsent
{
    public function consentSubjectType(): string
    {
        return static::class;
    }

    public function consentSubjectId(): string
    {
        return (string) $this->getKey();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function grantConsent(string $purpose, ?DateTimeInterface $expiresAt = null, array $metadata = []): ConsentRecord
    {
        return app(ConsentManager::class)->grant(
            $this->consentSubjectType(),
            $this->consentSubjectId(),
            $purpose,
            $expiresAt,
            $metadata,
        );
    }

    public function withdrawConsent(string $purpose): ConsentRecord
    {
        return app(ConsentManager::class)->withdraw(
            $this->consentSubjectType(),
            $this->consentSubjectId(),
            $purpose,
        );
    }

    public function hasConsent(string $purpose): bool
    {
        return app(ConsentManager::class)->isGranted(
            $this->consentSubjectType(),
            $this->consentSubjectId(),
            $purpose,
        );
    }
}
