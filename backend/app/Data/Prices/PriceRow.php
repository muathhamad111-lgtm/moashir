<?php

declare(strict_types=1);

namespace App\Data\Prices;

use App\Data\Finance\PriceQuote;
use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\ServicePriceVersion;

final readonly class PriceRow
{
    public function __construct(
        public PaymentPurpose $purpose,
        public PathPricing $pricing,
        public ?ServicePriceVersion $inForce,
        public ?ServicePriceVersion $scheduled,
        public int $historyTotal,
        public ?PriceQuote $inForceQuote,
        public ?PriceQuote $scheduledQuote,
    ) {}
}
