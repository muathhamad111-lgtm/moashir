<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Finance\PriceQuote;
use App\Data\Paths\PathPricing;
use App\Enums\Assessments\PathAvailability;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Award\AwardCycle;
use App\Models\Finance\ServicePriceVersion;
use App\Services\Award\AwardWindowReader;
use App\Services\Finance\FinanceSettings;
use App\Services\Prices\PriceVersions;
use App\Support\ValueObjects\Money;

final readonly class PathPricingReader
{
    public function __construct(private PriceVersions $versions, private FinanceSettings $finance, private AwardWindowReader $windows) {}

    public function self(): PathPricing
    {
        return $this->of(PaymentPurpose::SelfAssessment);
    }

    public function accredited(): PathPricing
    {
        return $this->of(PaymentPurpose::Accredited);
    }

    public function award(?AwardCycle $feeCycle): PathPricing
    {
        $row = $this->of(PaymentPurpose::Award);

        return $feeCycle instanceof AwardCycle ? $this->priced($feeCycle->entryFee(), $row->availability) : $row;
    }

    public function currentAward(): PathPricing
    {
        return $this->award($this->windows->read()->feeCycle());
    }

    /**
     * @return array<string, PathPricing>
     */
    public function allPaths(): array
    {
        return [
            PaymentPurpose::SelfAssessment->value => $this->self(),
            PaymentPurpose::Accredited->value => $this->accredited(),
            PaymentPurpose::Award->value => $this->currentAward(),
            PaymentPurpose::Bag->value => $this->of(PaymentPurpose::Bag),
        ];
    }

    public function forOrder(PaymentPurpose $purpose, ?AwardCycle $cycle): PathPricing
    {
        return $purpose === PaymentPurpose::Award ? $this->award($cycle) : $this->of($purpose);
    }

    public function of(PaymentPurpose $purpose): PathPricing
    {
        $version = $this->versions->inForce($purpose);

        if (! $version instanceof ServicePriceVersion) {
            return $purpose->isFreeUntilPriced() ? PathPricing::free() : PathPricing::unset();
        }

        return $this->priced($version->price(), $version->availability);
    }

    public function quoteOf(Money $price): PriceQuote
    {
        return PriceQuote::of($price, $this->finance->vatRate());
    }

    private function priced(Money $price, PathAvailability $availability): PathPricing
    {
        return $price->isPositive() ? new PathPricing($this->quoteOf($price), false, $availability) : new PathPricing(null, true, $availability);
    }
}
