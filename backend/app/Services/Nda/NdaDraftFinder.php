<?php

declare(strict_types=1);

namespace App\Services\Nda;

use App\Enums\Identity\TermsKind;
use App\Exceptions\Nda\NdaRejected;
use App\Models\Identity\TermsVersion;

/**
 * The agreement keeps at most one draft at a time (a partial unique index), so the admin endpoints address it
 * without an id.
 */
final class NdaDraftFinder
{
    public function find(): ?TermsVersion
    {
        return TermsVersion::query()->ofKind(TermsKind::Nda)->drafts()->first();
    }

    public function findOrFail(): TermsVersion
    {
        return $this->find() ?? throw NdaRejected::noDraft();
    }
}
