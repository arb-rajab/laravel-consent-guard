<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Casts;

use ArbRajab\ConsentGuard\Consent\ConsentManager;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentSubject;
use ArbRajab\ConsentGuard\Consent\Exceptions\ConsentRequiredException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Gates a single Eloquent attribute behind a named consent purpose:
 *
 *     protected $casts = [
 *         'marketing_email' => ConsentRequired::class.':marketing_email',
 *     ];
 *
 * The purpose string is arbitrary and defined by the host app (see
 * config/consent-guard.php's `consent.purposes`) — this package attaches
 * no GDPR/DSAR meaning to it. Both get() and set() check consent before
 * doing anything else, so a controller can't accidentally read or
 * overwrite a gated field just by not remembering to check first.
 *
 * The model the cast is attached to is the consent *subject* — it must
 * implement ConsentSubject (typically via Concerns\HasConsent). This is
 * intentionally not configurable to some other, unrelated model: a cast
 * only ever sees the model it's declared on, so that model is the only
 * thing it can meaningfully check consent against.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
class ConsentRequired implements CastsAttributes
{
    public function __construct(private readonly string $purpose) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $this->assertConsent($model, $key);

        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $this->assertConsent($model, $key);

        return [$key => $value];
    }

    private function assertConsent(Model $model, string $key): void
    {
        if (! $model instanceof ConsentSubject) {
            throw ConsentRequiredException::ambiguousSubject($key);
        }

        $subjectId = $model->consentSubjectId();

        if ($subjectId === '') {
            throw ConsentRequiredException::ambiguousSubject($key);
        }

        $granted = app(ConsentManager::class)->isGranted(
            $model->consentSubjectType(),
            $subjectId,
            $this->purpose,
        );

        if (! $granted) {
            throw ConsentRequiredException::consentNotGranted($key, $this->purpose);
        }
    }
}
