<?php

declare(strict_types=1);

namespace App\Support\Certificates;

use App\Models\Certificates\Certificate;
use App\Support\Periods\LocalDay;

final class CertificateSummaryPresenter
{
    /**
     * @return array<string, mixed>|null
     */
    public static function present(?Certificate $certificate): ?array
    {
        if (! $certificate instanceof Certificate) {
            return null;
        }

        return [
            'id' => $certificate->public_id,
            'number' => $certificate->number,
            'level' => $certificate->level?->value,
            'status' => $certificate->status->value,
            'issued_at' => $certificate->issued_at->toIso8601String(),
            'valid_until' => $certificate->valid_until?->toDateString(),
            'valid_until_at' => $certificate->validUntilInstant()?->toIso8601String(),
            'is_expired' => $certificate->isExpiredOn(LocalDay::today()),
        ];
    }
}
