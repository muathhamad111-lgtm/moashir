<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\ServicePrices;
use App\Support\Finance\PriceQuotePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ServicePrices $resource
 */
final class ServicePricesResource extends JsonResource
{
    /**
     * @return array{accredited: array<string, string>|null, bag: array<string, string>|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'accredited' => PriceQuotePresenter::present($this->resource->accredited),
            'bag' => PriceQuotePresenter::present($this->resource->bag),
        ];
    }
}
