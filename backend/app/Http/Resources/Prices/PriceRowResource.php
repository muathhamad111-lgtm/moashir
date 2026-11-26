<?php

declare(strict_types=1);

namespace App\Http\Resources\Prices;

use App\Data\Prices\PriceRow;
use App\Support\Prices\PriceRowPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PriceRow $resource
 */
final class PriceRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return PriceRowPresenter::present($this->resource, config()->string('app.display_timezone'));
    }
}
