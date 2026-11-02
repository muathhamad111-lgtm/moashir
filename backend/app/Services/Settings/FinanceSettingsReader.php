<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\FinanceSettingsView;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\Ability;
use App\Enums\Settings\FinanceSettingField;
use App\Models\Identity\User;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\ServicePriceQuotes;

final readonly class FinanceSettingsReader
{
    public function __construct(private ServicePriceQuotes $quotes, private FinanceSettings $settings) {}

    public function read(User $viewer): FinanceSettingsView
    {
        $vatRate = $this->settings->vatRate();

        return new FinanceSettingsView(
            $this->quotes->quoteOf(PaymentPurpose::Accredited, $vatRate),
            $this->quotes->quoteOf(PaymentPurpose::Bag, $vatRate),
            $vatRate,
            $this->settings->orderValidityMinutes(),
            $this->settings->orderValidityLimits(),
            array_values(array_filter(
                FinanceSettingField::cases(),
                static fn (FinanceSettingField $field): bool => $viewer->can(Ability::EditFinanceSettingField->value, $field),
            )),
        );
    }
}
