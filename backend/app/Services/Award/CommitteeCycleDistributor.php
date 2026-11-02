<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\CommitteeDistribution;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAverage;
use App\Services\Assessments\AssessmentRowLock;
use App\Support\ScalarValue;

final readonly class CommitteeCycleDistributor
{
    public function __construct(
        private CommitteeRequestsQuery $requests,
        private CommitteeDistributor $distributor,
        private CommitteeProgressSync $progress,
        private AssessmentRowLock $rowLock,
    ) {}

    public function distributeAll(AwardCycle $lockedCycle, ?int $actorId): CommitteeDistribution
    {
        $distribution = new CommitteeDistribution($lockedCycle->id);

        if (! $lockedCycle->status->isCommitteeStage()) {
            return $distribution;
        }

        foreach ($this->requests->distributable($lockedCycle->id)->orderBy('id')->pluck('id') as $assessmentId) {
            $locked = $this->rowLock->lock(ScalarValue::int($assessmentId))->setRelation('awardCycle', $lockedCycle);
            $distribution = $distribution->merge($this->distributor->distribute($locked, $actorId));
        }

        return $distribution;
    }

    /**
     * @param  list<int>  $assessmentIds
     * @return list<int>
     */
    public function resync(AwardCycle $lockedCycle, array $assessmentIds, ?int $actorId): array
    {
        $recomputed = [];

        foreach ($assessmentIds as $assessmentId) {
            $locked = $this->rowLock->lock($assessmentId)->setRelation('awardCycle', $lockedCycle);

            if ($this->progress->sync($locked, $actorId) instanceof CommitteeAverage) {
                $recomputed[] = $assessmentId;
            }
        }

        return $recomputed;
    }

    public function hasCommitteeRequests(AwardCycle $cycle): bool
    {
        return $this->requests->inCommittee($cycle->id)->exists();
    }
}
