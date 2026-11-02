<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\AssessmentScores;
use App\Enums\Assessments\ScoreLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentScores $resource
 */
final class AssessmentScoresResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $scores = $this->resource;
        $scale = $scores->assessment->scale_kind;

        return [
            'assessment' => new AssessmentResource($scores->assessment)->toArray($request),
            'scale' => ['kind' => $scale->value, 'maximum' => $scale->maximum()],
            'scores' => $this->scoresByLevel($scores),
            'progress' => ['scored' => $scores->scoredCount(), 'required' => $scores->requiredCount()],
        ];
    }

    /**
     * @return array{subs: object, points: object}
     */
    private function scoresByLevel(AssessmentScores $scores): array
    {
        $byLevel = [ScoreLevel::Sub->value => [], ScoreLevel::Point->value => []];

        foreach ($scores->sheet->entries() as $scored) {
            $byLevel[$scored->unit->level->value][(string) $scored->unit->id] = [
                'raw_value' => $scored->raw->toDecimal(),
                'percent' => $scored->raw->percent()->toDisplay(),
            ];
        }

        return ['subs' => (object) $byLevel[ScoreLevel::Sub->value], 'points' => (object) $byLevel[ScoreLevel::Point->value]];
    }
}
