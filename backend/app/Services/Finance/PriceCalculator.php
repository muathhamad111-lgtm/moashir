<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Finance\ServicePriceSource;
use App\Data\Finance\PriceQuote;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Finance\PaymentRejected;

final readonly class PriceCalculator
{
    public function __construct(private ServicePriceSource $prices, private FinanceSettings $settings) {}

    public function quote(PaymentPurpose $purpose): PriceQuote
    {
        $price = $this->prices->priceBeforeVatOf($purpose) ?? throw PaymentRejected::priceNotConfigured();

        return PriceQuote::of($price, $this->settings->vatRate());
    }
}
