<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Finance\ServicePriceSource;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Money;
use InvalidArgumentException;

final readonly class SettingsServicePriceSource implements ServicePriceSource
{
    public function __construct(private SettingsStore $settings) {}

    public function priceBeforeVatOf(PaymentPurpose $purpose): ?Money
    {
        $value = ScalarValue::string($this->settings->get($this->keyOf($purpose)));

        if ($value === '') {
            return null;
        }

        try {
            $price = new Money($value);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $price->isPositive() ? $price : null;
    }

    private function keyOf(PaymentPurpose $purpose): SettingKey
    {
        return match ($purpose) {
            PaymentPurpose::Accredited => SettingKey::AccreditedPrice,
            PaymentPurpose::Bag => SettingKey::BagPrice,
        };
    }
}
