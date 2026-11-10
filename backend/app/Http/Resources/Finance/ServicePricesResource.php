<?php

declare(strict_types=1);

namespace App\Http\Resources\Finance;

use App\Data\Finance\ServicePrices;
use App\Data\Paths\PathPricing;
use App\Support\Finance\PriceQuotePresenter;
use App\Support\Paths\PathPricingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ServicePrices $resource
 */
final class ServicePricesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'accredited' => PriceQuotePresenter::present($this->resource->accredited),
            'bag' => PriceQuotePresenter::present($this->resource->bag),
            'paths' => array_map(static fn (PathPricing $pricing): array => PathPricingPresenter::present($pricing), $this->resource->paths),
        ];
    }
}
