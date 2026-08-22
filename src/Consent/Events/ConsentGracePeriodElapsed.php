<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Events;

use ArbRajab\ConsentGuard\Consent\ConsentRecord;

/**
 * Dispatched by consent-guard:sweep-expired-consent for each ConsentRecord
 * whose withdrawal/expiry has passed its configured grace period. This
 * package deliberately does not know, and has no business knowing, what a
 * host application's own consent-gated fields or rows are — it ships the
 * ConsentRequired cast so a host app can *declare* which fields are gated,
 * but retention (nulling a column, anonymizing a row, deleting a record
 * entirely) is domain-specific behavior only the host app can decide. This
 * event is that decision point: a host app listens for it and acts on its
 * own schema however its own domain requires.
 */
class ConsentGracePeriodElapsed
{
    public function __construct(
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly string $purpose,
        public readonly ConsentRecord $consentRecord,
    ) {}
}
