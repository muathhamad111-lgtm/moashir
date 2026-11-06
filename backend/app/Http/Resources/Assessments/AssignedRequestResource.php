<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\ReviewClockPresenter;
use App\Support\Assessments\StatusMask;
use App\Support\Award\CyclePresenter;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Assessment $resource
 */
final class AssignedRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource;
        $assignment = $assessment->assignments->last();

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            ...PathPresenter::of($assessment->path),
            'status' => StatusMask::statusFor($assessment, $request->user()),
            'entity' => [...EntityPresenter::summary($assessment->entity), 'region' => EntityPresenter::place($assessment->entity->region)],
            'assigned_at' => $assignment instanceof AssessmentAssignment ? $assignment->created_at->toIso8601String() : null,
            'started_at' => $assignment instanceof AssessmentAssignment ? $assignment->started_at?->toIso8601String() : null,
            'completion_deadline' => $assessment->openCompletionRequest?->deadline_at->toIso8601String(),
            'award_cycle' => CyclePresenter::summary($assessment->awardCycle),
            ...ReviewClockPresenter::of($assessment),
        ];
    }
}
