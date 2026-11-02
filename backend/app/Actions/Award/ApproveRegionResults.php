<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\SecretaryDecisionDraft;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\SecretaryDecisionKind;
use App\Events\Award\RegionResultsApproved;
use App\Exceptions\Award\ResultsRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Award\AwardRegionApproval;
use App\Models\Entities\Region;
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

final readonly class ApproveRegionResults
{
    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $cycleLock,
        private AwardResultsQuery $results,
        private AssessmentRowLock $rowLock,
        private AssessmentStateMachine $stateMachine,
        private SecretaryDecisionRecorder $decisions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Region $region, ?string $reason): AwardRegionApproval
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $region): AwardRegionApproval {
            $locked = $this->cycleLock->lock($cycle->id);
            $this->assertOpen($locked, $region);
            $approved = $this->lockedApproved($locked, $region);
            $winners = array_values(array_filter($approved, static fn (Assessment $assessment): bool => $assessment->ranking?->regional_rank !== null));

            if ($winners === []) {
                throw ResultsRejected::noWinner();
            }

            foreach ($approved as $assessment) {
                $this->settle($assessment, $actor);
            }

            $approval = AwardRegionApproval::query()->create([
                'award_cycle_id' => $locked->id,
                'region_id' => $region->id,
                'winners_count' => count($winners),
                'approved_by_user_id' => $actor->id,
                'approved_at' => Date::now(),
            ]);
            $decision = $this->decisions->record($actor, new SecretaryDecisionDraft(SecretaryDecisionKind::ApproveRegion, $locked->id, regionId: $region->id));
            $this->audit->record(new AuditEntry(AuditEvent::AwardRegionApproved, $locked, [], [
                'region_id' => $region->id,
                'winners' => array_map(static fn (Assessment $assessment): int => $assessment->id, $winners),
                'non_winners' => count($approved) - count($winners),
                'secretary_decision_id' => $decision->id,
            ]));

            RegionResultsApproved::dispatch($locked, $region, count($winners), $actor->id);

            return $approval;
        });
    }

    private function assertOpen(AwardCycle $locked, Region $region): void
    {
        if ($locked->isNationalApproved()) {
            throw ResultsRejected::nationalLocked();
        }

        if (AwardRegionApproval::query()->where('award_cycle_id', $locked->id)->where('region_id', $region->id)->current()->exists()) {
            throw ResultsRejected::regionAlreadyApproved();
        }

        $pending = $this->results->unsettled($locked->id, AdvancedAssessmentStatus::resultStates(), $region->id)->orderBy('id')->get(['id', 'request_number', 'status', 'path']);

        if ($pending->isNotEmpty()) {
            throw ResultsRejected::requestsPending(array_values($pending->map(static fn (Assessment $assessment): array => [
                'id' => $assessment->id,
                'number' => $assessment->request_number,
                'status' => (string) $assessment->status->value,
            ])->all()));
        }
    }

    /**
     * @return list<Assessment>
     */
    private function lockedApproved(AwardCycle $cycle, Region $region): array
    {
        $ids = $this->results->inStatus($cycle->id, AdvancedAssessmentStatus::Approved, $region->id)->orderBy('id')->pluck('id');

        return array_values($ids->map(fn (mixed $id): Assessment => $this->rowLock->lock(ScalarValue::int($id))->load('ranking'))->all());
    }

    private function settle(Assessment $assessment, User $actor): void
    {
        if (! $assessment->ranking instanceof AwardRanking || $assessment->ranking->regional_rank === null) {
            $this->stateMachine->transition($assessment, AdvancedAssessmentStatus::NonWinner, AssessmentVerb::ApproveRegion, $actor->id);

            return;
        }

        $this->stateMachine->transition($assessment, AdvancedAssessmentStatus::RegionalWinner, AssessmentVerb::ApproveRegion, $actor->id);
        $this->stateMachine->transition($assessment, AdvancedAssessmentStatus::QualifiedForNational, AssessmentVerb::QualifyNational, null);
    }
}
