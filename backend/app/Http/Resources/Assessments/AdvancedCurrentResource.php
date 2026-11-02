<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\AdvancedCurrent;
use App\Http\Resources\Award\PublicCycleResource;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AdvancedCurrent $resource
 */
final class AdvancedCurrentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $this->resource;
        $cycle = $current->eligibility->cycle;

        return [
            'cycle' => $cycle instanceof AwardCycle ? new PublicCycleResource($cycle)->toArray($request) : null,
            'eligibility' => $current->eligibility->toArray(),
            'request' => $this->present($current->request, $request),
            'previous' => $this->present($current->previous, $request),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function present(?Assessment $assessment, Request $request): ?array
    {
        if (! $assessment instanceof Assessment) {
            return null;
        }

        return [
            ...new AssessmentResource($assessment)->toArray($request),
            'condition_failure' => $assessment->condition_failure,
        ];
    }
}
