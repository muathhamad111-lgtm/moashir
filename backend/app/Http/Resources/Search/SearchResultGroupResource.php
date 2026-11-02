<?php

declare(strict_types=1);

namespace App\Http\Resources\Search;

use App\Contracts\Search\SearchHit;
use App\Data\Search\SearchResultGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SearchResultGroup $resource
 */
final class SearchResultGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->resource->type->value,
            'total' => $this->resource->total,
            'items' => array_map(static fn (SearchHit $hit): array => $hit->toArray(), $this->resource->hits),
        ];
    }
}
