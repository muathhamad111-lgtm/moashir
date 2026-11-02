<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\StaffActionReason;
use App\Services\Entities\EntityFreezeGuard;

final readonly class SecretaryGate
{
    public function __construct(
        private StaffActionReason $reason,
        private AssessmentRowLock $rowLock,
        private EntityFreezeGuard $freezeGuard,
    ) {}

    public function enter(User $actor, AwardCycle $cycle, Assessment $assessment, string $verb, ?string $reason, AdvancedAssessmentStatus ...$allowed): Assessment
    {
        $this->reason->record($actor, $reason);
        $lockedCycle = AwardCycle::query()->whereKey($cycle->id)->sharedLock()->firstOrFail();
        $locked = $this->rowLock->lock($assessment->id)->setRelation('awardCycle', $lockedCycle);
        $this->freezeGuard->assertNotFrozen($locked->entity);

        if (! in_array($locked->status, $allowed, true)) {
            throw IllegalStateTransition::from((string) $locked->status->value, $verb);
        }

        return $locked;
    }
}
