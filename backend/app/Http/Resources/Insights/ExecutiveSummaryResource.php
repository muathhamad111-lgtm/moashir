<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use App\Data\Insights\ExecutiveSummaryView;
use App\Support\Insights\ExecutiveSummaryPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ExecutiveSummaryView $resource
 */
final class ExecutiveSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ExecutiveSummaryPresenter::presentView($this->resource);
    }
}
