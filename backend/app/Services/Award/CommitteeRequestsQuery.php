<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;

final readonly class CommitteeRequestsQuery
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<Assessment>
     */
    public function distributable(int $cycleId): Builder
    {
        return $this->freezeGuard->excludeFrozen(Assessment::query()
            ->inCycle($cycleId)
            ->whereIn('status', AdvancedAssessmentStatus::valuesOf(AdvancedAssessmentStatus::QualifiedForCommittee, ...AdvancedAssessmentStatus::committeeStates())));
    }

    /**
     * @return Builder<Assessment>
     */
    public function inCommittee(int $cycleId): Builder
    {
        return $this->freezeGuard->excludeFrozen(Assessment::query()
            ->inCycle($cycleId)
            ->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::committeeStates())));
    }
}
