<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Entities\EntityFreezeGuard;

final readonly class DraftExpirer
{
    public function __construct(private AssessmentStateMachine $stateMachine, private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return list<string>
     */
    public function expireDraftsOf(AwardCycle $cycle, ?int $actorId): array
    {
        $expired = [];
        $drafts = $this->freezeGuard->excludeFrozen(Assessment::query()->inCycle($cycle->id)->where('status', AdvancedAssessmentStatus::Draft))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($drafts as $draft) {
            $this->stateMachine->transition($draft, AdvancedAssessmentStatus::Expired, AssessmentVerb::ExpireDraft, $actorId);
            $expired[] = $draft->request_number;
        }

        return $expired;
    }
}
