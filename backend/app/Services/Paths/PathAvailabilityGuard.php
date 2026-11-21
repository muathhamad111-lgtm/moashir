<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Paths\PathRejected;

final class PathAvailabilityGuard
{
    public function assertAvailable(PaymentPurpose $path, PathPricing $pricing): void
    {
        if (! $pricing->isAvailable()) {
            throw PathRejected::unavailable($path, $pricing);
        }
    }
}
