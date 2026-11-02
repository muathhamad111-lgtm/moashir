<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Data\Finance\PriceQuote;
use App\Enums\Settings\FinanceSettingField;
use App\Support\ValueObjects\VatRate;

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
    ) {}
}
