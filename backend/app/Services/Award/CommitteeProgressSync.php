<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Events\Award\CommitteeAverageReady;
use App\Events\Award\CycleProgressChanged;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAverage;
use App\Services\Assessments\AssessmentStateMachine;

final readonly class CommitteeProgressSync
{
    private const array COLLECTING = [AdvancedAssessmentStatus::UnderCommitteeAssessment, AdvancedAssessmentStatus::ReturnedToCommittee];

    private const array REVIEWING = [AdvancedAssessmentStatus::AwaitingSecretaryApproval, AdvancedAssessmentStatus::CommitteeDiscussion];

    public function __construct(
        private CommitteeTallyReader $tallies,
        private CommitteeAverageRecorder $averages,
        private CommitteeDiscussionCanceller $discussions,
        private AssessmentStateMachine $stateMachine,
    ) {}

    public function sync(Assessment $locked, ?int $actorId): ?CommitteeAverage
    {
        $status = $locked->status;

        if (! $status instanceof AdvancedAssessmentStatus || ! $status->isInCommittee()) {
            return null;
        }

        $tally = $this->tallies->tally($locked);

        if ($tally->isComplete()) {
            $average = $this->averages->record($locked, $tally, $actorId, false)->average;
            $this->completeCollection($locked, $status, $actorId);

            return $average;
        }

        $this->resumeCollection($locked, $status, $actorId);

        return null;
    }

    private function completeCollection(Assessment $locked, AdvancedAssessmentStatus $status, ?int $actorId): void
    {
        if (! in_array($status, self::COLLECTING, true)) {
            return;
        }

        $this->stateMachine->transition($locked, AdvancedAssessmentStatus::AwaitingSecretaryApproval, AssessmentVerb::CompleteCommittee, $actorId);
        CommitteeAverageReady::dispatch($locked, $actorId);
        CycleProgressChanged::dispatch((int) $locked->award_cycle_id);
    }

    private function resumeCollection(Assessment $locked, AdvancedAssessmentStatus $status, ?int $actorId): void
    {
        if ($locked->committee_average_id !== null) {
            $locked->forceFill(['committee_average_id' => null])->save();
        }

        if (! in_array($status, self::REVIEWING, true)) {
            return;
        }

        $this->discussions->cancelOpen($locked);
        $this->stateMachine->transition($locked, AdvancedAssessmentStatus::UnderCommitteeAssessment, AssessmentVerb::ResumeCommittee, $actorId);
        CycleProgressChanged::dispatch((int) $locked->award_cycle_id);
    }
}
