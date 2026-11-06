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
        public ?VatRegistrationNumberEdit $vatRegistrationNumber = null,
    ) {}

    /**
     * @return array<value-of<FinanceSettingField>, string|int|null>
     */
    public function values(): array
    {
        $values = array_filter([
            FinanceSettingField::AccreditedPrice->value => $this->accreditedPrice?->amount,
            FinanceSettingField::BagPrice->value => $this->bagPrice?->amount,
            FinanceSettingField::VatRate->value => $this->vatRate?->percent,
            FinanceSettingField::OrderValidityMinutes->value => $this->orderValidityMinutes,
        ], static fn (string|int|null $value): bool => $value !== null);

        if ($this->vatRegistrationNumber instanceof VatRegistrationNumberEdit) {
            $values[FinanceSettingField::VatRegistrationNumber->value] = $this->vatRegistrationNumber->number?->value;
        }

        return $values;
    }

    public function isEmpty(): bool
    {
        return $this->values() === [];
    }
}
