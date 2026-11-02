<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\FilteringQueueFilters;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;

final readonly class FilteringQueueQuery
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<Assessment>
     */
    public function filtered(AwardCycle $cycle, FilteringQueueFilters $filters): Builder
    {
        $query = $this->freezeGuard->excludeFrozen(Assessment::query()
            ->inCycle($cycle->id)
            ->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::filteringVisibleStates())));

        if ($filters->status === AdvancedAssessmentStatus::QualifiedForCommittee) {
            $query->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::qualifiedStates()));
        } elseif ($filters->status instanceof AdvancedAssessmentStatus) {
            $query->where('status', $filters->status);
        }

        if ($filters->regionId !== null) {
            $query->where('region_id', $filters->regionId);
        }

        if ($filters->search instanceof SearchTerm) {
            $query->matchingSearch($filters->search);
        }

        return $query;
    }
}
