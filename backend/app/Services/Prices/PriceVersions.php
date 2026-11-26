<?php

declare(strict_types=1);

namespace App\Services\Prices;

use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\ServicePriceVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final class PriceVersions
{
    public function inForce(PaymentPurpose $purpose, ?CarbonInterface $moment = null): ?ServicePriceVersion
    {
        return ServicePriceVersion::query()->of($purpose)->active()->effectiveBy($moment ?? Date::now())->first();
    }

    public function scheduled(PaymentPurpose $purpose, ?CarbonInterface $moment = null): ?ServicePriceVersion
    {
        return ServicePriceVersion::query()->of($purpose)->active()->scheduledAfter($moment ?? Date::now())->first();
    }
}
