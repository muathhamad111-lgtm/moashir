<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Data\Award\CommitteeScores;
use App\Enums\Assessments\ScoreLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CommitteeScores $resource
 */
final class CommitteeScoresResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $scores = $this->resource;
        $scale = $scores->assignment->assessment->scale_kind;
        $byLevel = [ScoreLevel::Sub->value => [], ScoreLevel::Point->value => []];

        foreach ($scores->sheet->entries() as $scored) {
            $byLevel[$scored->unit->level->value][(string) $scored->unit->id] = [
                'raw_value' => $scored->raw->toDecimal(),
                'percent' => $scored->raw->percent()->toDisplay(),
            ];
        }

        return [
            'assignment_id' => $scores->assignment->id,
            'status' => $scores->assignment->status->value,
            'scale' => ['kind' => $scale->value, 'maximum' => $scale->maximum()],
            'scores' => ['subs' => (object) $byLevel[ScoreLevel::Sub->value], 'points' => (object) $byLevel[ScoreLevel::Point->value]],
            'progress' => ['scored' => $scores->scoredCount(), 'required' => $scores->requiredCount()],
            'percent' => $scores->assignment->percent?->toDisplay(),
        ];
    }
}
