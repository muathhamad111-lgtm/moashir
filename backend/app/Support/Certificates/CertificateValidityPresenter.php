<?php

declare(strict_types=1);

namespace App\Support\Certificates;

use App\Models\Certificates\Certificate;
use App\Support\Periods\LocalDay;

final class CertificateValidityPresenter
{
    /**
     * @return array{validity: string|null, days_until_expiry: int|null}
     */
    public static function present(Certificate $certificate): array
    {
        $status = $certificate->validityOn(LocalDay::today(), config()->integer('moashir.certificates.expiring_soon_days'));

        return [
            'validity' => $status?->state->value,
            'days_until_expiry' => $status?->daysLeft,
        ];
    }
}
