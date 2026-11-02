<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\SecretaryDecisionDraft;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\SecretaryDecisionKind;
use App\Events\Award\NationalResultsApproved;
use App\Exceptions\Award\ResultsRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\AwardResultsQuery;
use App\Services\Award\SecretaryDecisionRecorder;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ApproveNationalResults
{
    private const string VERB = 'approve_national';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $cycleLock,
        private AwardResultsQuery $results,
        private AssessmentRowLock $rowLock,
        private AssessmentStateMachine $stateMachine,
        private SecretaryDecisionRecorder $decisions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, ?string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle): AwardCycle {
            $locked = $this->cycleLock->lock($cycle->id);
            $this->assertOpen($locked);
            $candidates = $this->lockedCandidates($locked);
            $winners = array_values(array_filter($candidates, static fn (Assessment $assessment): bool => $assessment->ranking?->national_rank !== null));

            if ($winners === []) {
                throw ResultsRejected::noWinner();
            }

            foreach ($candidates as $candidate) {
                $next = $candidate->ranking?->national_rank !== null ? AdvancedAssessmentStatus::NationalWinner : AdvancedAssessmentStatus::NationalNonWinner;
                $this->stateMachine->transition($candidate, $next, AssessmentVerb::ApproveNational, $actor->id);
            }

            $previous = $locked->status;
            $locked->forceFill(['status' => AwardCycleStatus::Approved, 'national_approved_at' => Date::now(), 'national_approved_by_user_id' => $actor->id])->save();
            $decision = $this->decisions->record($actor, new SecretaryDecisionDraft(SecretaryDecisionKind::ApproveNational, $locked->id));
            $this->audit->record(new AuditEntry(AuditEvent::AwardNationalApproved, $locked, ['status' => $previous->value], [
                'status' => AwardCycleStatus::Approved->value,
                'winners' => array_map(static fn (Assessment $assessment): int => $assessment->id, $winners),
                'non_winners' => count($candidates) - count($winners),
                'secretary_decision_id' => $decision->id,
            ]));

            NationalResultsApproved::dispatch($locked, $actor->id);

            return $locked;
        });
    }

    private function assertOpen(AwardCycle $locked): void
    {
        if ($locked->isNationalApproved()) {
            throw ResultsRejected::nationalLocked();
        }

        if (! $locked->status->isCommitteeStage() || ! $locked->status->allowsTransitionTo(AwardCycleStatus::Approved)) {
            throw IllegalStateTransition::from($locked->status->value, self::VERB);
        }

        $pendingRegions = $this->results->unsettled($locked->id, AdvancedAssessmentStatus::nationalStates())->distinct()->orderBy('region_id')->pluck('region_id');

        if ($pendingRegions->isNotEmpty()) {
            throw ResultsRejected::regionsPending(array_values($pendingRegions->map(static fn (mixed $id): int => ScalarValue::int($id))->all()));
        }
    }

    /**
     * @return list<Assessment>
     */
    private function lockedCandidates(AwardCycle $cycle): array
    {
        $ids = $this->results->inStatus($cycle->id, AdvancedAssessmentStatus::QualifiedForNational)->orderBy('id')->pluck('id');

        return array_values($ids->map(fn (mixed $id): Assessment => $this->rowLock->lock(ScalarValue::int($id))->load('ranking'))->all());
    }
}
