<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Enums\Award\AwardCycleStatus;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Award\AwardCycle;
use App\Services\Award\CycleStageSync;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

final readonly class AdvanceCycleSchedule
{
    public function __construct(
        private OpenCycleSubmissions $open,
        private CloseCycleSubmissions $close,
        private StartCommitteeAssessment $startCommittee,
        private CycleStageSync $stages,
    ) {}

    /**
     * @return array{opened: int, closed: int, committees_started: int}
     */
    public function handle(): array
    {
        $result = ['closed' => $this->closeEndedSubmissions(), 'opened' => $this->openStartedCycles(), 'committees_started' => $this->startDueCommittees()];
        $this->syncCommitteeStages();

        return $result;
    }

    private function startDueCommittees(): int
    {
        $started = 0;
        $due = AwardCycle::query()->where('status', AwardCycleStatus::SubmissionClosed)->where('committee_start', '<=', Date::now())->orderBy('id')->get();

        foreach ($due as $cycle) {
            try {
                $this->startCommittee->handle(null, $cycle, null);
                $started++;
            } catch (CommitteeRejected $rejected) {
                Log::warning('award.committee_not_started', ['award_cycle_id' => $cycle->id, 'code' => $rejected->error()->code]);
            }
        }

        return $started;
    }

    private function syncCommitteeStages(): void
    {
        $cycleIds = AwardCycle::query()->whereIn('status', [AwardCycleStatus::CommitteeAssessment, AwardCycleStatus::AwaitingSecretary])->pluck('id');

        foreach ($cycleIds as $cycleId) {
            $this->stages->sync(ScalarValue::int($cycleId));
        }
    }

    private function closeEndedSubmissions(): int
    {
        $closed = 0;
        $due = AwardCycle::query()->where('status', AwardCycleStatus::SubmissionOpen)->where('submission_end', '<=', Date::now())->orderBy('id')->get();

        foreach ($due as $cycle) {
            $this->close->handle(null, $cycle, null);
            $closed++;
        }

        return $closed;
    }

    private function openStartedCycles(): int
    {
        $now = Date::now();
        $due = AwardCycle::query()
            ->where('status', AwardCycleStatus::Draft)
            ->where('submission_start', '<=', $now)
            ->where('submission_end', '>', $now)
            ->orderBy('submission_start')
            ->orderBy('id')
            ->get();

        foreach ($due as $cycle) {
            try {
                $this->open->handle(null, $cycle, null);

                return 1;
            } catch (AwardRejected $rejected) {
                Log::warning('award.cycle_not_opened', ['award_cycle_id' => $cycle->id, 'code' => $rejected->error()->code]);
            }
        }

        return 0;
    }
}
