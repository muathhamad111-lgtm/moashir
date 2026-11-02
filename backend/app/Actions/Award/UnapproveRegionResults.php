<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\SecretaryDecisionDraft;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\SecretaryDecisionKind;
use App\Exceptions\Award\ResultsRejected;
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

final readonly class UnapproveRegionResults
{
    private const array REVERTED = [AdvancedAssessmentStatus::RegionalWinner, AdvancedAssessmentStatus::QualifiedForNational, AdvancedAssessmentStatus::NonWinner];

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $cycleLock,
        private AwardResultsQuery $results,
        private AssessmentRowLock $rowLock,
        private AssessmentStateMachine $stateMachine,
        private SecretaryDecisionRecorder $decisions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Region $region, string $reason): AwardRegionApproval
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $region, $reason): AwardRegionApproval {
            $locked = $this->cycleLock->lock($cycle->id);

            if ($locked->isNationalApproved()) {
                throw ResultsRejected::nationalLocked();
            }

            $approval = AwardRegionApproval::query()->where('award_cycle_id', $locked->id)->where('region_id', $region->id)->current()->lockForUpdate()->first()
                ?? throw ResultsRejected::regionNotApproved();
            $reverted = $this->revert($locked, $region, $actor);
            $approval->forceFill(['unapproved_at' => Date::now(), 'unapproved_by_user_id' => $actor->id, 'unapprove_reason' => $reason])->save();
            $decision = $this->decisions->record($actor, new SecretaryDecisionDraft(SecretaryDecisionKind::UnapproveRegion, $locked->id, note: $reason, regionId: $region->id));
            $this->audit->record(new AuditEntry(AuditEvent::AwardRegionUnapproved, $locked, ['region_id' => $region->id, 'approved' => true], [
                'approved' => false,
                'reverted_requests' => $reverted,
                'secretary_decision_id' => $decision->id,
            ]));

            return $approval;
        });
    }

    /**
     * @return list<int>
     */
    private function revert(AwardCycle $cycle, Region $region, User $actor): array
    {
        $reverted = [];

        foreach (self::REVERTED as $status) {
            foreach ($this->results->inStatus($cycle->id, $status, $region->id)->orderBy('id')->pluck('id') as $id) {
                $locked = $this->rowLock->lock(ScalarValue::int($id));
                $this->stateMachine->override($locked, AdvancedAssessmentStatus::Approved, AssessmentVerb::UnapproveRegion, $actor->id);
                AwardRanking::query()->where('assessment_id', $locked->id)->update(['national_rank' => null, 'updated_at' => Date::now()]);
                $reverted[] = $locked->id;
            }
        }

        return $reverted;
    }
}
