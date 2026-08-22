<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Http\Middleware;

use ArbRajab\ConsentGuard\Consent\ConsentManager;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentSubject;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `Route::middleware('consent-guard:marketing_email')`.
 * Denies ahead of the controller unless the authenticated user both
 * implements ConsentSubject and has valid, non-withdrawn, non-expired
 * consent for the named purpose.
 *
 * Fails closed on every ambiguous case, not just "consent withdrawn":
 * no authenticated user, a user that doesn't implement ConsentSubject,
 * and any exception ConsentManager::isGranted() itself already swallows
 * into `false` (see that class's docblock) all deny. There is
 * deliberately no code path here that lets a request through when this
 * middleware can't establish "yes, consent is valid" affirmatively — see
 * ConsentFailClosedFaultInjectionTest for the fault-injection proof of
 * this against a lookup failure specifically.
 */
class EnsureConsentGranted
{
    public function __construct(private readonly ConsentManager $consent) {}

    public function handle(Request $request, Closure $next, string $purpose): Response
    {
        $subject = $request->user();

        if (! $subject instanceof ConsentSubject) {
            return $this->deny($purpose);
        }

        if (! $this->consent->isGranted($subject->consentSubjectType(), $subject->consentSubjectId(), $purpose)) {
            return $this->deny($purpose);
        }

        return $next($request);
    }

    private function deny(string $purpose): Response
    {
        $status = (int) config('consent-guard.consent.deny_status', 403);
        $message = (string) config('consent-guard.consent.deny_message', 'This action requires valid consent.');

        return new JsonResponse(['message' => $message, 'purpose' => $purpose], $status);
    }
}
