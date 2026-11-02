<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Award\AwardCycleStatus;
use App\Events\Award\CommitteeAssessorOverdue;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class FlagOverdueCommitteeAssessments
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    public function handle(): int
    {
        $flagged = 0;
        $cycles = AwardCycle::query()
            ->whereIn('status', [AwardCycleStatus::CommitteeAssessment, AwardCycleStatus::AwaitingSecretary])
            ->where('committee_end', '<', Date::now())
            ->pluck('id');

        foreach ($cycles as $cycleId) {
            $flagged += $this->flagCycle(ScalarValue::int($cycleId));
        }

        return $flagged;
    }

    private function flagCycle(int $cycleId): int
    {
        return DB::transaction(function () use ($cycleId): int {
            $overdue = CommitteeAssignment::query()
                ->where('award_cycle_id', $cycleId)
                ->pending()
                ->whereNull('overdue_notified_at')
                ->whereIn('cycle_committee_assessor_id', CycleCommitteeAssessor::query()->select('id')->active())
                ->whereIn('assessment_id', $this->freezeGuard->excludeFrozen(Assessment::query()->select('id')->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::committeeStates()))))
                ->lockForUpdate()
                ->get();

            foreach ($overdue->groupBy('cycle_committee_assessor_id') as $memberId => $rows) {
                CommitteeAssignment::query()->whereKey($rows->modelKeys())->update(['overdue_notified_at' => Date::now()]);
                CommitteeAssessorOverdue::dispatch(CycleCommitteeAssessor::query()->findOrFail(ScalarValue::int($memberId)), $rows->count());
            }

            return $overdue->count();
        });
    }
}
