<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;

final class CurrentTermsVersions
{
    public function of(TermsKind $kind): ?TermsVersion
    {
        return TermsVersion::query()->current()->where('kind', $kind->value)->first();
    }
}
