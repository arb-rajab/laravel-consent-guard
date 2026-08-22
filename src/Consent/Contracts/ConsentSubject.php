<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Contracts;

/**
 * Implemented by whatever model consent is recorded *about* — typically
 * (but not necessarily) the authenticatable user. Pairs with
 * Concerns\HasConsent the same way Laravel's own MustVerifyEmail pairs
 * with a trait: `implements ConsentSubject` is what lets the
 * ConsentRequired cast and EnsureConsentGranted middleware type-check the
 * model instead of duck-typing a method call, and what lets them fail
 * closed (deny) when a model doesn't implement it at all, rather than
 * guessing at an identity to check consent for.
 */
interface ConsentSubject
{
    public function consentSubjectType(): string;

    public function consentSubjectId(): string;
}
