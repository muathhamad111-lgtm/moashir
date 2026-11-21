<?php

declare(strict_types=1);

namespace App\Http\Resources\Prices;

use App\Data\Prices\PriceRow;
use App\Data\Prices\PriceRows;
use App\Support\ValueObjects\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PriceRows $resource
 */
final class PriceRowsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => Money::SAUDI_RIYAL,
            'vat_rate' => $this->resource->vatRate->percent,
            'rows' => array_map(static fn (PriceRow $row): array => new PriceRowResource($row)->toArray($request), $this->resource->rows),
            'limits' => $this->resource->window->toArray(),
        ];
    }
}
