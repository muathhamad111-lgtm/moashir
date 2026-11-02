<?php

declare(strict_types=1);

namespace App\Support\Finance;

use App\Data\Finance\PriceQuote;

final class PriceQuotePresenter
{
    /**
     * @return array{price_before_vat: string, vat_rate: string, vat_amount: string, total: string, currency: string}|null
     */
    public static function present(?PriceQuote $quote): ?array
    {
        if (! $quote instanceof PriceQuote) {
            return null;
        }

        return [
            'price_before_vat' => $quote->priceBeforeVat->amount,
            'vat_rate' => $quote->vatRate->percent,
            'vat_amount' => $quote->vatAmount->amount,
            'total' => $quote->total->amount,
            'currency' => $quote->total->currency,
        ];
    }
}
