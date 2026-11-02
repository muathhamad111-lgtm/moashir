<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Settings\FinanceSettingField;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\VatRate;

final readonly class FinanceSettingsUpdate
{
    public function __construct(
        public ?Money $accreditedPrice,
        public ?Money $bagPrice,
        public ?VatRate $vatRate,
        public ?int $orderValidityMinutes,
        public ?string $reason,
    ) {}

    /**
     * @return array<value-of<FinanceSettingField>, string|int>
     */
    public function values(): array
    {
        return array_filter([
            FinanceSettingField::AccreditedPrice->value => $this->accreditedPrice?->amount,
            FinanceSettingField::BagPrice->value => $this->bagPrice?->amount,
            FinanceSettingField::VatRate->value => $this->vatRate?->percent,
            FinanceSettingField::OrderValidityMinutes->value => $this->orderValidityMinutes,
        ], static fn (string|int|null $value): bool => $value !== null);
    }

    public function isEmpty(): bool
    {
        return $this->values() === [];
    }
}
