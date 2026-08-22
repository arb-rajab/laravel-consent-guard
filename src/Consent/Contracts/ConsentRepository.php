<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Contracts;

use ArbRajab\ConsentGuard\Consent\ConsentRecord;

/**
 * The one seam between ConsentManager's fail-closed isGranted() check and
 * however consent records are actually looked up. Exists so a test can
 * rebind this to an implementation that throws — the fault-injection
 * proof for the fail-closed guarantee (see
 * tests/Feature/Consent/ConsentFailClosedFaultInjectionTest.php) needs a
 * deterministic way to simulate "the lookup itself failed," and swapping
 * this binding is a cleaner way to do that than corrupting a real
 * database connection mid-test.
 */
interface ConsentRepository
{
    public function find(string $subjectType, string $subjectId, string $purpose): ?ConsentRecord;
}
