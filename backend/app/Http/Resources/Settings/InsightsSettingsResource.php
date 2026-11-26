<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Insights\InsightsRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read InsightsRules $resource
 */
final class InsightsSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rules = $this->resource;

        return [
            'gap_thresholds' => $rules->gaps->toArray(),
            'maturity_bands' => $rules->bands->toArray(),
            'aligned' => $rules->bands->developingFrom->equals($rules->gaps->criticalBelow) && $rules->bands->leadingFrom->equals($rules->gaps->strengthFrom),
            'effective_since' => $rules->effectiveSince?->toIso8601String(),
        ];
    }
}
