<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use App\Data\Insights\StrengthsGapsReport;
use App\Support\Insights\StrengthsGapsPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StrengthsGapsReport $resource
 */
final class StrengthsGapsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return StrengthsGapsPresenter::present($this->resource);
    }
}
