<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Data\Finance\PriceQuote;
use App\Support\ValueObjects\Money;
use App\Support\ValueObjects\VatRate;

final class RequestVatRate
{
    private ?VatRate $rate = null;

    public function __construct(private readonly FinanceSettings $settings) {}

    public function quoteOf(Money $price): PriceQuote
    {
        $this->rate ??= $this->settings->vatRate();

        return PriceQuote::of($price, $this->rate);
    }
}
