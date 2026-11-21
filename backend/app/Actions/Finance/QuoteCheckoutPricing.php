<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Award\AwardCycle;
use App\Services\Paths\PathPricingReader;

final readonly class QuoteCheckoutPricing
{
    public function __construct(private PathPricingReader $pricing) {}

    public function handle(?PaymentPurpose $purpose, ?int $awardCycleId): ?PathPricing
    {
        if (! $purpose instanceof PaymentPurpose) {
            return null;
        }

        $cycle = $purpose->isPerCycle() ? AwardCycle::query()->find($awardCycleId) ?? throw PaymentRejected::awardCycleNotFound() : null;

        return $this->pricing->forOrder($purpose, $cycle);
    }
}
