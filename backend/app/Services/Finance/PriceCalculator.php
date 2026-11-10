<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\PriceQuote;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Award\AwardCycle;
use App\Services\Paths\PathAvailabilityGuard;
use App\Services\Paths\PathPricingReader;

final readonly class PriceCalculator
{
    public function __construct(private PathPricingReader $pricing, private PathAvailabilityGuard $availability) {}

    public function quote(PaymentPurpose $purpose, ?AwardCycle $cycle = null): PriceQuote
    {
        $pricing = $this->pricing->forOrder($purpose, $cycle);
        $this->availability->assertAvailable($purpose, $pricing);

        return $pricing->price ?? throw PaymentRejected::notRequired($purpose);
    }
}
