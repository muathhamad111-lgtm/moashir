<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Events\Award\CommitteeRequestsDistributed;
use App\Events\Award\CycleProgressChanged;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Award\CommitteeDistributor;
use Illuminate\Support\Facades\DB;

final readonly class DistributeQualifiedRequest
{
    public function __construct(private AssessmentRowLock $rowLock, private CommitteeDistributor $distributor) {}

    public function handle(Assessment $assessment, ?int $actorId): void
    {
        DB::transaction(function () use ($assessment, $actorId): void {
            $cycle = AwardCycle::query()->whereKey($assessment->award_cycle_id)->sharedLock()->first();

            if (! $cycle instanceof AwardCycle || ! $cycle->status->isCommitteeStage()) {
                return;
            }

            $locked = $this->rowLock->lock($assessment->id)->setRelation('awardCycle', $cycle);

            if ($locked->isFrozen()) {
                return;
            }

            $distribution = $this->distributor->distribute($locked, $actorId);

            if (! $distribution->isEmpty()) {
                CommitteeRequestsDistributed::dispatch($distribution, $actorId);
            }

            CycleProgressChanged::dispatch($cycle->id);
        });
    }
}
