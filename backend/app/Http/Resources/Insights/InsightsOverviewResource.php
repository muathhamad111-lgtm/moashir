<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use App\Data\Insights\InsightsOverview;
use App\Support\Insights\InsightsOverviewPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read InsightsOverview $resource
 */
final class InsightsOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return InsightsOverviewPresenter::present($this->resource);
    }
}
