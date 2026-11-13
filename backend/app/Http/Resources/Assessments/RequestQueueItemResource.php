<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Models\Entities\Region;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\ReviewClockPresenter;
use App\Support\Assessments\StatusMask;
use App\Support\Award\CyclePresenter;
use App\Support\Certificates\EntityLevelPresenter;
use App\Support\Entities\EntityPresenter;
use App\Support\Periods\PeriodPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Assessment $resource
 */
final class RequestQueueItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assessment = $this->resource;
        $assignment = $assessment->activeAssignment;

        return [
            'id' => $assessment->id,
            'number' => $assessment->request_number,
            ...PathPresenter::of($assessment->path),
            'status' => StatusMask::statusFor($assessment, $request->user()),
            'is_frozen' => $assessment->isFrozen(),
            'entity' => [
                ...EntityPresenter::summary($assessment->entity),
                'level' => EntityLevelPresenter::levelOf($assessment->entity)?->value,
                'last_level' => EntityLevelPresenter::lastLevelOf($assessment->entity),
            ],
            'assessor' => $assignment instanceof AssessmentAssignment ? ['id' => $assignment->assessor_user_id, 'name' => $assignment->assessor->name] : null,
            'assigned_at' => $assignment?->created_at->toIso8601String(),
            'completion_deadline' => $assessment->openCompletionRequest?->deadline_at->toIso8601String(),
            'award_cycle' => CyclePresenter::summary($assessment->awardCycle),
            'region' => $assessment->region instanceof Region ? EntityPresenter::place($assessment->region) : null,
            ...ReviewClockPresenter::of($assessment),
            ...PeriodPresenter::attribution($assessment),
        ];
    }
}
