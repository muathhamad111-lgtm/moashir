<?php

declare(strict_types=1);

namespace App\Support\Prices;

use App\Data\Finance\PriceQuote;
use App\Data\Prices\PriceRow;
use App\Models\Finance\ServicePriceVersion;
use App\Support\Finance\PriceQuotePresenter;
use App\Support\Paths\PathPricingPresenter;

final class PriceRowPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(PriceRow $row, string $timezone): array
    {
        return [
            'key' => $row->purpose->value,
            'pricing' => PathPricingPresenter::present($row->pricing),
            'in_force' => self::versionOf($row->inForce, $row->inForceQuote, $timezone),
            'scheduled' => self::versionOf($row->scheduled, $row->scheduledQuote, $timezone),
            'history_total' => $row->historyTotal,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function versionOf(?ServicePriceVersion $version, ?PriceQuote $quote, string $timezone): ?array
    {
        if (! $version instanceof ServicePriceVersion) {
            return null;
        }

        return [...PriceVersionPresenter::present($version, $timezone), 'quote' => PriceQuotePresenter::present($quote)];
    }
}
