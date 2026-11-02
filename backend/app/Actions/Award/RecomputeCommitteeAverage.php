<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\RecordedAverage;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\CommitteeAverageRecorder;
use App\Services\Award\CommitteeTallyReader;
use App\Services\Award\SecretaryGate;
use Illuminate\Support\Facades\DB;

final readonly class RecomputeCommitteeAverage
{
    public function __construct(
        private SecretaryGate $gate,
        private CommitteeTallyReader $tallies,
        private CommitteeAverageRecorder $averages,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, ?string $reason): RecordedAverage
    {
        return DB::transaction(function () use ($actor, $cycle, $assessment, $reason): RecordedAverage {
            $locked = $this->gate->enter($actor, $cycle, $assessment, 'recompute_average', $reason, AdvancedAssessmentStatus::AwaitingSecretaryApproval, AdvancedAssessmentStatus::CommitteeDiscussion);
            $tally = $this->tallies->tally($locked);

            if (! $tally->isComplete()) {
                throw CommitteeRejected::incomplete($tally->pendingIds());
            }

            return $this->averages->record($locked, $tally, $actor->id, true);
        });
    }
}
