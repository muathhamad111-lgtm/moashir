<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\CycleRecordScope;
use App\Services\Award\InitialAssessorLinks;

/**
 * Tells the assign dialog why an award request may list no assessor: its cycle links none yet. A cycle manager also
 * gets the cycle id, so the dialog can send them to the cycle page to link some.
 */
final readonly class AssignmentCycleHint
{
    public function __construct(private InitialAssessorLinks $links, private CycleRecordScope $scope) {}

    /**
     * @return array{has_initial_assessors: bool, cycle_id: int|null}|null
     */
    public function for(User $actor, ?Assessment $assessment): ?array
    {
        $cycle = $assessment?->awardCycle;

        if (! $cycle instanceof AwardCycle) {
            return null;
        }

        return [
            'has_initial_assessors' => $this->links->hasActive($cycle->id),
            'cycle_id' => $this->scope->canManage($actor, $cycle) ? $cycle->id : null,
        ];
    }
}
