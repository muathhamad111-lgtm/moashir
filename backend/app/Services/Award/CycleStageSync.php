<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Support\Facades\DB;

final readonly class CycleStageSync
{
    public function __construct(private EntityFreezeGuard $freezeGuard, private AuditRecorder $audit) {}

    public function sync(int $cycleId): ?AwardCycle
    {
        return DB::transaction(function () use ($cycleId): ?AwardCycle {
            $cycle = AwardCycle::query()->whereKey($cycleId)->lockForUpdate()->first();

            if (! $cycle instanceof AwardCycle || ! $cycle->status->isCommitteeStage()) {
                return $cycle;
            }

            $target = $this->hasCommitteeWork($cycleId) ? AwardCycleStatus::CommitteeAssessment : AwardCycleStatus::AwaitingSecretary;

            if ($target === $cycle->status) {
                return $cycle;
            }

            $previous = $cycle->status;
            $cycle->forceFill(['status' => $target])->save();
            $this->audit->record(new AuditEntry(AuditEvent::CycleStageChanged, $cycle, ['status' => $previous->value], ['status' => $target->value]));

            return $cycle;
        });
    }

    private function hasCommitteeWork(int $cycleId): bool
    {
        $pending = array_values(array_filter(AdvancedAssessmentStatus::cases(), static fn (AdvancedAssessmentStatus $status): bool => $status->precedesSecretaryReview()));

        return $this->freezeGuard->excludeFrozen(Assessment::query()->inCycle($cycleId)->whereIn('status', AdvancedAssessmentStatus::valuesOf(...$pending)))->exists();
    }
}
