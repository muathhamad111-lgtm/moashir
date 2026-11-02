<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\RankStage;
use App\Exceptions\Award\ResultsRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardRanking;
use App\Models\Award\AwardRegionApproval;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class SetAwardRank
{
    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $cycleLock,
        private AssessmentRowLock $rowLock,
        private EntityFreezeGuard $freezeGuard,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, Assessment $assessment, RankStage $stage, ?int $rank, ?string $reason): AwardRanking
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $assessment, $stage, $rank): AwardRanking {
            $lockedCycle = $this->cycleLock->lock($cycle->id);
            $locked = $this->rowLock->lock($assessment->id);
            $this->freezeGuard->assertNotFrozen($locked->entity);
            $this->assertRankable($lockedCycle, $locked, $stage);

            $ranking = AwardRanking::query()->where('assessment_id', $locked->id)->lockForUpdate()->firstOrFail();
            $this->assertFree($ranking, $stage, $rank);
            $before = $ranking->rankAt($stage);
            $ranking->forceFill([$stage->column() => $rank, 'ranked_by_user_id' => $actor->id])->save();
            $this->audit->record(new AuditEntry(
                $stage === RankStage::Regional ? AuditEvent::AwardRegionalRankSet : AuditEvent::AwardNationalRankSet,
                $locked,
                [$stage->column() => $before],
                [$stage->column() => $rank],
            ));

            return $ranking->setRelation('assessment', $locked);
        });
    }

    private function assertRankable(AwardCycle $cycle, Assessment $locked, RankStage $stage): void
    {
        if ($stage === RankStage::Regional) {
            if ($locked->status !== AdvancedAssessmentStatus::Approved) {
                $this->rejectLockedOrIllegal($locked, AwardRegionApproval::query()->where('award_cycle_id', $cycle->id)->where('region_id', $locked->region_id)->current()->exists());
            }

            return;
        }

        if ($cycle->isNationalApproved()) {
            throw ResultsRejected::nationalLocked();
        }

        if ($locked->status !== AdvancedAssessmentStatus::QualifiedForNational) {
            throw IllegalStateTransition::from((string) $locked->status->value, 'rank_national');
        }
    }

    private function rejectLockedOrIllegal(Assessment $locked, bool $regionApproved): never
    {
        if ($regionApproved) {
            throw ResultsRejected::regionLocked();
        }

        throw IllegalStateTransition::from((string) $locked->status->value, 'rank_regional');
    }

    private function assertFree(AwardRanking $ranking, RankStage $stage, ?int $rank): void
    {
        if ($rank === null) {
            return;
        }

        $holder = AwardRanking::query()
            ->where('award_cycle_id', $ranking->award_cycle_id)
            ->when($stage === RankStage::Regional, static fn (Builder $query): Builder => $query->where('region_id', $ranking->region_id))
            ->where($stage->column(), $rank)
            ->whereKeyNot($ranking->id)
            ->first();

        if ($holder instanceof AwardRanking) {
            throw ResultsRejected::rankTaken($holder->assessment_id);
        }
    }
}
