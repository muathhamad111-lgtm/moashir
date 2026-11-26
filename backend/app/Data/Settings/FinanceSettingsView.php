<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Data\Finance\PriceQuote;
use App\Enums\Settings\FinanceSettingField;
use App\Support\ValueObjects\VatRate;
use App\Support\ValueObjects\VatRegistrationNumber;

final readonly class FinanceSettingsView
{
    /**
     * @param  array{min: int, max: int}  $orderValidityLimits
     * @param  list<FinanceSettingField>  $editableFields
     */
    public function __construct(
        public ?PriceQuote $accredited,
        public ?PriceQuote $bag,
        public VatRate $vatRate,
        public int $orderValidityMinutes,
        public array $orderValidityLimits,
        public array $editableFields,
        public ?VatRegistrationNumber $vatRegistrationNumber = null,
    ) {}

    public function needsVatRegistrationReminder(): bool
    {
        return ! $this->vatRegistrationNumber instanceof VatRegistrationNumber && $this->vatRate->basisPoints() > 0;
    }
}
