<?php

declare(strict_types=1);

namespace App\Services\Prices;

use App\Data\Finance\PriceQuote;
use App\Data\Prices\PriceRow;
use App\Data\Prices\PriceRows;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Finance\ServicePriceVersion;
use App\Services\Finance\FinanceSettings;
use App\Services\Paths\PathPricingReader;
use Illuminate\Support\Facades\Date;

final readonly class PriceRowReader
{
    public const array ROW_ORDER = [PaymentPurpose::SelfAssessment, PaymentPurpose::Accredited, PaymentPurpose::Award, PaymentPurpose::Bag];

    private const array RELATIONS = ['setBy'];

    public function __construct(
        private PriceVersions $versions,
        private PathPricingReader $pricing,
        private FinanceSettings $finance,
        private PriceEditWindowReader $window,
    ) {}

    public function all(): PriceRows
    {
        return new PriceRows(
            array_map(fn (PaymentPurpose $purpose): PriceRow => $this->read($purpose), self::ROW_ORDER),
            $this->finance->vatRate(),
            $this->window->current(),
        );
    }

    public function read(PaymentPurpose $purpose): PriceRow
    {
        $now = Date::now();
        $inForce = $this->versions->inForce($purpose, $now)?->load(self::RELATIONS);
        $scheduled = $this->versions->scheduled($purpose, $now)?->load(self::RELATIONS);

        return new PriceRow(
            $purpose,
            $this->pricing->of($purpose),
            $inForce,
            $scheduled,
            ServicePriceVersion::query()->of($purpose)->count(),
            $this->quoteOf($inForce),
            $this->quoteOf($scheduled),
        );
    }

    public function timezone(): string
    {
        return $this->window->current()->timezone;
    }

    private function quoteOf(?ServicePriceVersion $version): ?PriceQuote
    {
        return $version instanceof ServicePriceVersion ? $this->pricing->quoteOf($version->price()) : null;
    }
}
