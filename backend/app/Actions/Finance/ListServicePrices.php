<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Finance\ServicePrices;
use App\Enums\Finance\PaymentPurpose;
use App\Services\Finance\FinanceSettings;
use App\Services\Finance\ServicePriceQuotes;

final readonly class ListServicePrices
{
    public function __construct(private ServicePriceQuotes $quotes, private FinanceSettings $settings) {}

    public function handle(): ServicePrices
    {
        $vatRate = $this->settings->vatRate();

        return new ServicePrices(
            $this->quotes->quoteOf(PaymentPurpose::Accredited, $vatRate),
            $this->quotes->quoteOf(PaymentPurpose::Bag, $vatRate),
        );
    }
}
