<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Settings\UpdateFinanceSettings;
use App\Contracts\Finance\ServicePriceSource;
use App\Data\Settings\FinanceSettingsUpdate;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Identity\User;
use App\Support\ValueObjects\Money;

final readonly class DemoPriceInstaller
{
    private const string ACCREDITED_PRICE_BEFORE_VAT = '2500.00';

    public function __construct(private ServicePriceSource $prices, private UpdateFinanceSettings $updateSettings) {}

    public function install(User $systemAdmin): void
    {
        if ($this->prices->priceBeforeVatOf(PaymentPurpose::Accredited) instanceof Money) {
            return;
        }

        $this->updateSettings->handle($systemAdmin, new FinanceSettingsUpdate(
            new Money(self::ACCREDITED_PRICE_BEFORE_VAT),
            null,
            null,
            null,
            null,
        ));
    }
}
