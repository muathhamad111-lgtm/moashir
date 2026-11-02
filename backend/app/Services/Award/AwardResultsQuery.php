<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\RankStage;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardRanking;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;

final readonly class AwardResultsQuery
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<AwardRanking>
     */
    public function rankings(int $cycleId, RankStage $stage, ?int $regionId = null): Builder
    {
        $states = $stage === RankStage::Regional ? AdvancedAssessmentStatus::resultStates() : AdvancedAssessmentStatus::nationalStates();

        return AwardRanking::query()
            ->with(['assessment.entity', 'assessment.region'])
            ->where('award_cycle_id', $cycleId)
            ->when($regionId !== null, static fn (Builder $query): Builder => $query->where('region_id', $regionId))
            ->whereIn('assessment_id', $this->freezeGuard->excludeFrozen(Assessment::query()->select('id')->inCycle($cycleId)->whereIn('status', AdvancedAssessmentStatus::valuesOf(...$states))));
    }

    /**
     * @param  list<AdvancedAssessmentStatus>  $settled
     * @return Builder<Assessment>
     */
    public function unsettled(int $cycleId, array $settled, ?int $regionId = null): Builder
    {
        $open = array_values(array_filter(
            AdvancedAssessmentStatus::cases(),
            static fn (AdvancedAssessmentStatus $status): bool => $status->isOpen() && $status->isInRequestQueue() && ! in_array($status, $settled, true),
        ));

        return $this->freezeGuard->excludeFrozen(Assessment::query()
            ->inCycle($cycleId)
            ->when($regionId !== null, static fn (Builder $query): Builder => $query->where('region_id', $regionId))
            ->whereIn('status', AdvancedAssessmentStatus::valuesOf(...$open)));
    }

    /**
     * @return Builder<Assessment>
     */
    public function inStatus(int $cycleId, AdvancedAssessmentStatus $status, ?int $regionId = null): Builder
    {
        return $this->freezeGuard->excludeFrozen(Assessment::query()
            ->inCycle($cycleId)
            ->when($regionId !== null, static fn (Builder $query): Builder => $query->where('region_id', $regionId))
            ->where('status', $status));
    }
}
