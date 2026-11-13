<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\AdvancedEligibility;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Events\Assessments\AdvancedConditionsRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Assessments\AdvancedRequestOpener;

final readonly class AdvancedRejectionRecorder
{
    public function __construct(private AdvancedRequestOpener $opener) {}

    public function record(User $actor, Entity $entity, AwardCycle $cycle, AdvancedEligibility $eligibility): Assessment
    {
        $recorded = Assessment::query()->ownedBy($entity->id)->inCycle($cycle->id)->where('status', AdvancedAssessmentStatus::RejectedConditions)->first();

        if ($recorded instanceof Assessment) {
            return $recorded;
        }

        $rejected = $this->opener->recordRejection($entity, $cycle, $eligibility, $actor->id);
        AdvancedConditionsRejected::dispatch($rejected, $actor->id);

        return $rejected;
    }
}
