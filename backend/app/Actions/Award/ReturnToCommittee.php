<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\SecretaryDecisionDraft;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\SecretaryDecisionKind;
use App\Events\Award\CycleProgressChanged;
use App\Events\Award\ReturnedToCommittee;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeDiscussionCanceller;
use App\Services\Award\CommitteeTallyReader;
use App\Services\Award\SecretaryDecisionRecorder;
use App\Services\Award\SecretaryGate;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ReturnToCommittee
{
    public function __construct(
        private SecretaryGate $gate,
        private CommitteeTallyReader $tallies,
        private CommitteeDiscussionCanceller $discussions,
        private SecretaryDecisionRecorder $decisions,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, string $note, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $cycle, $assessment, $note, $reason): Assessment {
            $locked = $this->gate->enter($actor, $cycle, $assessment, AssessmentVerb::ReturnToCommittee->value, $reason, AdvancedAssessmentStatus::AwaitingSecretaryApproval, AdvancedAssessmentStatus::CommitteeDiscussion);
            $previous = $locked->status;
            $this->discussions->cancelOpen($locked);
            $reopened = $this->reopenCounted($locked);
            $decision = $this->decisions->record($actor, new SecretaryDecisionDraft(SecretaryDecisionKind::ReturnToCommittee, (int) $locked->award_cycle_id, $locked->id, $note, $locked->committee_average_id));
            $locked->forceFill(['committee_average_id' => null]);
            $this->stateMachine->transition($locked, AdvancedAssessmentStatus::ReturnedToCommittee, AssessmentVerb::ReturnToCommittee, $actor->id);
            $this->audit->record(new AuditEntry(AuditEvent::AwardReturnedToCommittee, $locked, ['status' => $previous->value], [
                'status' => AdvancedAssessmentStatus::ReturnedToCommittee->value,
                'secretary_decision_id' => $decision->id,
                'reopened_assignment_ids' => $reopened,
            ]));

            ReturnedToCommittee::dispatch($locked, $decision, $actor->id);
            CycleProgressChanged::dispatch((int) $locked->award_cycle_id);

            return $locked;
        });
    }

    /**
     * @return list<int>
     */
    private function reopenCounted(Assessment $locked): array
    {
        $ids = array_map(static fn (CommitteeAssignment $assignment): int => $assignment->id, $this->tallies->tally($locked)->counted);

        CommitteeAssignment::query()->whereKey($ids)->where('status', CommitteeAssignmentStatus::Completed)->update([
            'status' => CommitteeAssignmentStatus::InProgress,
            'percent' => null,
            'total_score' => null,
            'submitted_at' => null,
        ]);
        CommitteeAssignment::query()->whereKey($ids)->update(['returned_at' => Date::now(), 'updated_at' => Date::now()]);

        return array_values($ids);
    }
}
