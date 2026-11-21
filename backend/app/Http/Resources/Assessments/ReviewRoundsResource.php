<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\ReviewRounds;
use App\Data\Assessments\RoundScore;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Support\Assessments\CompletionRequestPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ReviewRounds $resource
 */
final class ReviewRoundsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $rounds = $this->resource;

        return [
            'requests' => array_map(
                $this->request(...),
                $rounds->requests,
                array_keys($rounds->requests),
            ),
            'units' => (object) array_map(
                static fn (array $scores): array => array_map(static fn (RoundScore $round): array => [
                    'round' => $round->round,
                    'completion_request_id' => $round->score->completion_request_id,
                    'raw_value' => $round->score->raw_value,
                    'percent' => $round->score->percent->toDisplay(),
                    'scored_at' => $round->score->created_at->toIso8601String(),
                ], $scores),
                $rounds->units,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function request(AssessmentCompletionRequest $completion, int $position): array
    {
        return [
            'id' => $completion->id,
            'round' => ReviewRounds::roundAt($position),
            'status' => $completion->status->value,
            'notes' => $completion->notes,
            'requested_at' => $completion->created_at->toIso8601String(),
            'resolved_at' => $completion->resolved_at?->toIso8601String(),
            'deadline_at' => $completion->deadline_at->toIso8601String(),
            'expiry_outcome' => $completion->expiry_outcome->value,
            'items' => CompletionRequestPresenter::items($completion, $this->resource->subs),
        ];
    }
}
