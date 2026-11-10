<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Finance\PriceQuote;
use App\Data\Paths\PathPricing;
use App\Enums\Assessments\PathAvailability;
use App\Enums\Finance\PaymentPurpose;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\ServicePriceQuotes;

final readonly class PathPricingReader
{
    public function __construct(private ServicePriceQuotes $quotes, private FinanceSettings $finance) {}

    public function self(): PathPricing
    {
        return PathPricing::free();
    }

    public function accredited(): PathPricing
    {
        $quote = $this->quotes->quoteOf(PaymentPurpose::Accredited, $this->finance->vatRate());

        if (! $quote instanceof PriceQuote) {
            return new PathPricing(null, false, PathAvailability::Paused);
        }

        return $quote->priceBeforeVat->isPositive() ? new PathPricing($quote, false, PathAvailability::Available) : PathPricing::free();
    }

    public function award(): PathPricing
    {
        return PathPricing::free();
    }
}
