<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent;

use ArbRajab\ConsentGuard\Consent\Contracts\ConsentRepository;
use DateTimeInterface;
use Throwable;

/**
 * The single service both writes consent (grant/withdraw) and answers the
 * one question every other part of this feature is built around:
 * isGranted(). That question is fail-closed by design, matching the
 * principle documented in privacy-forge's ADR-0006 (read there for the
 * reasoning this package deliberately does not restate wholesale) — any
 * ambiguous or error state must resolve to "not granted," never to
 * "granted." Concretely: a missing record is not granted; a withdrawn
 * record is not granted; an expired record is not granted; and if the
 * lookup itself throws for any reason, that is *also* not granted, not a
 * bug to propagate and let a caller accidentally treat as "no consent
 * needed." See ConsentFailClosedFaultInjectionTest for the test that
 * proves the last case explicitly, by making the lookup throw on purpose.
 */
class ConsentManager
{
    public function __construct(private readonly ConsentRepository $repository) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function grant(
        string $subjectType,
        string|int $subjectId,
        string $purpose,
        ?DateTimeInterface $expiresAt = null,
        array $metadata = [],
    ): ConsentRecord {
        return ConsentRecord::query()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => (string) $subjectId,
                'purpose' => $purpose,
            ],
            [
                'granted_at' => now(),
                'withdrawn_at' => null,
                'expires_at' => $expiresAt,
                'metadata' => $metadata,
            ],
        );
    }

    public function withdraw(string $subjectType, string|int $subjectId, string $purpose): ConsentRecord
    {
        return ConsentRecord::query()->updateOrCreate(
            [
                'subject_type' => $subjectType,
                'subject_id' => (string) $subjectId,
                'purpose' => $purpose,
            ],
            [
                'withdrawn_at' => now(),
            ],
        );
    }

    public function isGranted(string $subjectType, string|int $subjectId, string $purpose): bool
    {
        try {
            $record = $this->repository->find($subjectType, (string) $subjectId, $purpose);

            return $record !== null && $record->isGranted();
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
