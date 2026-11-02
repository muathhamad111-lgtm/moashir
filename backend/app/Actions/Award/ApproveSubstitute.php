<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\SecretaryDecisionDraft;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Enums\Award\CommitteeVoidReason;
use App\Enums\Award\SecretaryDecisionKind;
use App\Events\Award\CommitteeSubstituteApproved;
use App\Exceptions\Award\CommitteeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeAssignmentVoider;
use App\Services\Award\CommitteeProgressSync;
use App\Services\Award\SecretaryDecisionRecorder;
use App\Services\Award\SecretaryGate;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ApproveSubstitute
{
    private const string VERB = 'approve_substitute';

    public function __construct(
        private SecretaryGate $gate,
        private CommitteeAssignmentVoider $voider,
        private CommitteeProgressSync $progress,
        private SecretaryDecisionRecorder $decisions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, CommitteeAssignment $substitution, ?string $note, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $cycle, $assessment, $substitution, $note, $reason): Assessment {
            $locked = $this->gate->enter($actor, $cycle, $assessment, self::VERB, $reason, ...AdvancedAssessmentStatus::committeeStates());
            $row = CommitteeAssignment::query()->whereKey($substitution->id)->lockForUpdate()->firstOrFail();

            if (! $row->isPendingSubstitute() || $row->isVoided()) {
                throw IllegalStateTransition::from($row->status->value, self::VERB);
            }

            if (! $row->isCompleted()) {
                throw CommitteeRejected::substituteNotCompleted();
            }

            $row->forceFill(['substitute_approved_at' => Date::now(), 'substitute_approved_by_user_id' => $actor->id])->save();
            $this->voider->void(CommitteeAssignment::query()->whereKey((int) $row->replaces_assignment_id), CommitteeVoidReason::Replaced);
            $this->settleReplacedMember((int) $row->replaces_assignment_id);
            $average = $this->progress->sync($locked, $actor->id);
            $decision = $this->decisions->record($actor, new SecretaryDecisionDraft(SecretaryDecisionKind::ApproveSubstitute, (int) $locked->award_cycle_id, $locked->id, $note, $average?->id, $row->id));
            $this->audit->record(new AuditEntry(AuditEvent::SubstituteApproved, $locked, ['replaced_assignment_id' => $row->replaces_assignment_id], [
                'substitute_assignment_id' => $row->id,
                'committee_average_id' => $average?->id,
                'secretary_decision_id' => $decision->id,
            ]));

            CommitteeSubstituteApproved::dispatch($row, $actor->id);

            return $locked;
        });
    }

    private function settleReplacedMember(int $replacedAssignmentId): void
    {
        $replaced = CommitteeAssignment::query()->findOrFail($replacedAssignmentId);
        $member = CycleCommitteeAssessor::query()->whereKey($replaced->cycle_committee_assessor_id)->lockForUpdate()->firstOrFail();
        $stillPending = CommitteeAssignment::query()
            ->whereIn('replaces_assignment_id', CommitteeAssignment::query()->select('id')->where('cycle_committee_assessor_id', $member->id))
            ->live()
            ->whereNull('substitute_approved_at')
            ->exists();

        if ($member->status === CommitteeMembershipStatus::Substituted && ! $stillPending) {
            $member->forceFill(['status' => CommitteeMembershipStatus::Excluded])->save();
        }
    }
}
