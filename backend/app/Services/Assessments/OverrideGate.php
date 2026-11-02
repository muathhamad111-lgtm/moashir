<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;

final readonly class OverrideGate
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private AssessmentRowLock $rowLock,
        private StaffActionReason $reason,
    ) {}

    public function enter(User $actor, Assessment $assessment, string $reason): Assessment
    {
        $this->freezeGuard->assertNotFrozen($assessment->entity);

        $locked = $this->rowLock->lock($assessment->id);
        $this->reason->record($actor, $reason);

        return $locked;
    }
}
