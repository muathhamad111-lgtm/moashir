<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\CommitteeAverage;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;
use App\Support\ScalarValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Assessment $resource
 */
final class SecretaryRequestRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource;
        $average = $assessment->committeeAverage;

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            'status' => $assessment->status->value,
            'is_frozen' => $assessment->isFrozen(),
            'entity' => EntityPresenter::summary($assessment->entity),
            'region' => $assessment->region instanceof Region ? EntityPresenter::place($assessment->region) : null,
            'initial_percent' => $assessment->currentResult instanceof AssessmentResultSnapshot ? $assessment->currentResult->percent->toDisplay() : null,
            'average' => $average instanceof CommitteeAverage ? self::average($average) : null,
            'committee' => [
                'assignments' => ScalarValue::int($assessment->getAttribute('live_assignments_count')),
                'completed' => ScalarValue::int($assessment->getAttribute('completed_assignments_count')),
                'pending_substitutions' => ScalarValue::int($assessment->getAttribute('pending_substitutions_count')),
            ],
            'submitted_at' => $assessment->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, version: int, percent: string, member_count: int, is_manual: bool, computed_at: string}
     */
    public static function average(CommitteeAverage $average): array
    {
        return [
            'id' => $average->id,
            'version' => $average->version,
            'percent' => $average->percent->toDisplay(),
            'member_count' => $average->member_count,
            'is_manual' => $average->is_manual,
            'computed_at' => $average->created_at->toIso8601String(),
        ];
    }
}
