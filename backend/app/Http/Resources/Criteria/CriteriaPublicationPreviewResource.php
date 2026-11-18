<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Data\Criteria\CriteriaChange;
use App\Data\Criteria\CriteriaPublicationPreview;
use App\Enums\Criteria\CriteriaEditScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CriteriaPublicationPreview $resource
 */
final class CriteriaPublicationPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $preview = $this->resource;

        return [
            'replaces' => $preview->replaces === null ? null : [
                'id' => $preview->replaces->id,
                'version_number' => $preview->replaces->version_number,
            ],
            'change_count' => count($preview->changes),
            'score_affecting_count' => $preview->scoreAffectingCount(),
            'changes' => array_map(static fn (CriteriaChange $change): array => [
                ...$change->toArray(),
                'label' => $preview->labelOf($change),
            ], $preview->changes),
            'rebound_open_assessments' => $this->reboundPerScope($preview->rebindableOpenAssessments),
        ];
    }

    /**
     * A list rather than a map keyed by scope: clients convert JSON keys, never values.
     *
     * @return list<array{scope: string, count: int}>
     */
    private function reboundPerScope(int $rebindable): array
    {
        return array_map(static fn (CriteriaEditScope $scope): array => [
            'scope' => $scope->value,
            'count' => $scope->rebindsOpenAssessments() ? $rebindable : 0,
        ], CriteriaEditScope::cases());
    }
}
