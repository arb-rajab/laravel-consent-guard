<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests\Fixtures;

use ArbRajab\ConsentGuard\Consent\ConsentRecord;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentRepository;
use RuntimeException;

/**
 * Stands in for a real lookup failure (a dropped database connection, a
 * query timeout, whatever) without needing to actually break a real
 * database mid-test. Used by ConsentFailClosedFaultInjectionTest to prove
 * ConsentManager::isGranted() and EnsureConsentGranted deny rather than
 * throw or — worse — silently allow when this happens.
 */
class ThrowingConsentRepository implements ConsentRepository
{
    public function find(string $subjectType, string $subjectId, string $purpose): ?ConsentRecord
    {
        throw new RuntimeException('Simulated consent lookup failure (e.g. a database outage).');
    }
}
