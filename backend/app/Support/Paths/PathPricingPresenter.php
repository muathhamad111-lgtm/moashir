<?php

declare(strict_types=1);

namespace App\Support\Paths;

use App\Data\Paths\PathPricing;
use App\Support\Finance\PriceQuotePresenter;

final class PathPricingPresenter
{
    /**
     * @return array{price: array<string, string>|null, is_free: bool, availability: string}
     */
    public static function present(PathPricing $pricing): array
    {
        return [
            'price' => PriceQuotePresenter::present($pricing->price),
            'is_free' => $pricing->isFree,
            'availability' => $pricing->availability->value,
        ];
    }
}
