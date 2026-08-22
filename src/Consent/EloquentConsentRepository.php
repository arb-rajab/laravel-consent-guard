<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent;

use ArbRajab\ConsentGuard\Consent\Contracts\ConsentRepository;

class EloquentConsentRepository implements ConsentRepository
{
    public function find(string $subjectType, string $subjectId, string $purpose): ?ConsentRecord
    {
        return ConsentRecord::query()
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('purpose', $purpose)
            ->first();
    }
}
