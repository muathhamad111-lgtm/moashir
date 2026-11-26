<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CycleAttributes;
use App\Data\Award\CycleSchedule;
use App\Enums\Award\AwardCycleStatus;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Support\ValueObjects\Percent;
use Illuminate\Support\Facades\Date;

final readonly class CycleChangeGuard
{
    public function __construct(private CycleFieldLocks $locks, private QualificationThresholdLock $thresholdLock) {}

    public function assertAllowed(User $actor, AwardCycle $locked, CycleAttributes $changes): void
    {
        $this->locks->assertEditable($actor, $locked->status, $changes);
        $this->assertThresholdOpen($locked, $changes);
        $this->assertSchedule($locked, $changes);
        $this->assertRegionsKeepRequests($locked, $changes->regionIds);
    }

    /**
     * Runs under the cycle row lock, which a filtering decision also takes: a decision and a threshold change never
     * interleave, so the threshold can never move once a decision is recorded. Re-sending the current value is fine.
     */
    private function assertThresholdOpen(AwardCycle $locked, CycleAttributes $changes): void
    {
        $score = $changes->disqualificationScore;

        if ($score instanceof Percent && ! $score->equals($locked->disqualification_score) && $this->thresholdLock->isLocked($locked->id)) {
            throw AwardRejected::thresholdLocked();
        }
    }

    private function assertSchedule(AwardCycle $locked, CycleAttributes $changes): void
    {
        if ($changes->dates === []) {
            return;
        }

        $schedule = CycleSchedule::of($locked)->with($changes->dates);
        $endsInPast = $locked->status === AwardCycleStatus::SubmissionOpen && $schedule->submissionEnd->lessThanOrEqualTo(Date::now());

        if (! $schedule->isOrdered() || $endsInPast) {
            throw AwardRejected::datesUnordered();
        }
    }

    /**
     * @param  list<int>|null  $regionIds
     */
    private function assertRegionsKeepRequests(AwardCycle $locked, ?array $regionIds): void
    {
        if ($regionIds === null) {
            return;
        }

        $removed = array_values(array_diff($locked->regions->modelKeys(), $regionIds));

        if ($removed !== [] && Assessment::query()->inCycle($locked->id)->whereIn('region_id', $removed)->exists()) {
            throw AwardRejected::regionHasRequests();
        }
    }
}
