<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ConsentRequiredException extends RuntimeException
{
    public static function consentNotGranted(string $field, string $purpose): self
    {
        return new self("Access to \"{$field}\" requires valid, non-withdrawn consent for purpose \"{$purpose}\".");
    }

    public static function ambiguousSubject(string $field): self
    {
        return new self(
            "Cannot determine the consent subject for \"{$field}\": the model must implement ".
            'ArbRajab\ConsentGuard\Consent\Contracts\ConsentSubject (typically via the HasConsent trait). '.
            'Failing closed rather than allowing access without a determinable subject.'
        );
    }

    /**
     * Laravel's exception handler calls render() automatically when it
     * exists, so a host app gets a sensible default (403, no stack trace
     * leaked) without needing its own exception-handler wiring — while
     * still being free to override that by catching this exception type
     * itself.
     */
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => $this->getMessage()], 403);
    }
}
