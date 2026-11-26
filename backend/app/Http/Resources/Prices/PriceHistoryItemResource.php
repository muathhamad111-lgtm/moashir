<?php

declare(strict_types=1);

namespace App\Http\Resources\Prices;

use App\Data\Prices\PriceHistoryItem;
use App\Models\Finance\ServicePriceVersion;
use App\Support\Prices\PriceVersionPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PriceHistoryItem $resource
 */
final class PriceHistoryItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $version = $this->resource->version;
        $previous = $version->previousVersion;

        return [
            ...PriceVersionPresenter::present($version, config()->string('app.display_timezone')),
            'state' => $this->resource->state->value,
            'cancelled_at' => $version->cancelled_at?->toIso8601String(),
            'cancelled_by' => PriceVersionPresenter::userOf($version->cancelledBy),
            'before' => $previous instanceof ServicePriceVersion ? [
                'price_before_vat' => $previous->price_before_vat,
                'availability' => $previous->availability->value,
            ] : null,
        ];
    }
}
