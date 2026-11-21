<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Contracts\Finance\ServicePriceSource;
use App\Data\Award\CycleDefaults;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\Percent;

final readonly class CycleDefaultsReader
{
    public function __construct(private SettingsStore $settings, private ServicePriceSource $prices) {}

    public function read(): CycleDefaults
    {
        $values = $this->settings->many([
            SettingKey::AdvancedMinEntityAgeYears,
            SettingKey::AdvancedDisqualificationScore,
            SettingKey::AdvancedInitialReviewSlaDays,
        ]);

        return new CycleDefaults(
            ScalarValue::int($values[SettingKey::AdvancedMinEntityAgeYears->value] ?? null, 3),
            Percent::of(ScalarValue::string($values[SettingKey::AdvancedDisqualificationScore->value] ?? null, '60.00')),
            max(1, ScalarValue::int($values[SettingKey::AdvancedInitialReviewSlaDays->value] ?? null, 14)),
            $this->prices->priceBeforeVatOf(PaymentPurpose::Award) ?? Money::zero(),
        );
    }
}
