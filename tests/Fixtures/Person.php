<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests\Fixtures;

use ArbRajab\ConsentGuard\Consent\Casts\ConsentRequired;
use ArbRajab\ConsentGuard\Consent\Concerns\HasConsent;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentSubject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for "any Eloquent model with a consent-gated field in a host
 * application" — a real, persisted model (unlike AuditLog's Order
 * fixture) because the ConsentRequired cast needs to run through Eloquent's
 * actual get/set attribute pipeline, not just be called directly.
 */
class Person extends Model implements ConsentSubject
{
    use HasConsent;
    use HasUuids;

    protected $table = 'people';

    protected $fillable = ['name', 'ssn'];

    protected $casts = [
        'ssn' => ConsentRequired::class.':background_check',
    ];
}
