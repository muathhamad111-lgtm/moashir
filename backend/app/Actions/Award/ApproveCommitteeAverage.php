<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\SecretaryDecisionDraft;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\SecretaryDecisionKind;
use App\Events\Award\CommitteeAverageApproved;
use App\Events\Award\CycleProgressChanged;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeAverageRecorder;
use App\Services\Award\CommitteeTallyReader;
use App\Services\Award\SecretaryDecisionRecorder;
use App\Services\Award\SecretaryGate;
use Illuminate\Support\Facades\DB;

final readonly class ApproveCommitteeAverage
{
    public function __construct(
        private SecretaryGate $gate,
        private CommitteeTallyReader $tallies,
        private CommitteeAverageRecorder $averages,
        private SecretaryDecisionRecorder $decisions,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, ?string $note, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $cycle, $assessment, $note, $reason): Assessment {
            $locked = $this->gate->enter($actor, $cycle, $assessment, AssessmentVerb::ApproveAverage->value, $reason, AdvancedAssessmentStatus::AwaitingSecretaryApproval);
            $tally = $this->tallies->tally($locked);

            if ($tally->pendingSubstitutes !== []) {
                throw CommitteeRejected::substitutionPending($tally->pendingSubstituteIds());
            }

            if (! $tally->isComplete()) {
                throw CommitteeRejected::incomplete($tally->pendingIds());
            }

            $average = $this->averages->record($locked, $tally, $actor->id, false)->average;
            AwardRanking::query()->create([
                'award_cycle_id' => $locked->award_cycle_id,
                'assessment_id' => $locked->id,
                'region_id' => $locked->region_id,
                'committee_average_id' => $average->id,
                'average_percent' => $average->percent,
            ]);
            $decision = $this->decisions->record($actor, new SecretaryDecisionDraft(SecretaryDecisionKind::ApproveAverage, (int) $locked->award_cycle_id, $locked->id, $note, $average->id));
            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::Approved, AssessmentVerb::ApproveAverage, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AwardAverageApproved, $locked, ['status' => AdvancedAssessmentStatus::AwaitingSecretaryApproval->value], [
                'status' => AdvancedAssessmentStatus::Approved->value,
                'committee_average_id' => $average->id,
                'percent' => $average->percent->toDecimal(),
                'secretary_decision_id' => $decision->id,
            ]));

            CommitteeAverageApproved::dispatch($locked, $actor->id);
            CycleProgressChanged::dispatch((int) $locked->award_cycle_id);

            return $locked;
        });
    }
}
