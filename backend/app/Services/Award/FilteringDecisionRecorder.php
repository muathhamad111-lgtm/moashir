<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\FilteringOutcome;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardCycle;
use App\Models\Award\FilteringDecision;
use App\Models\Identity\User;
use App\Support\ValueObjects\Percent;

final class FilteringDecisionRecorder
{
    public function record(User $actor, Assessment $locked, AwardCycle $cycle, FilteringOutcome $outcome, ?string $reason, ?string $note): FilteringDecision
    {
        $snapshot = $locked->currentResult;
        $initial = $snapshot instanceof AssessmentResultSnapshot ? $snapshot->percent : Percent::zero();

        return FilteringDecision::query()->create([
            'assessment_id' => $locked->id,
            'award_cycle_id' => $cycle->id,
            'result_snapshot_id' => $snapshot?->id,
            'outcome' => $outcome,
            'initial_percent' => $initial,
            'threshold_percent' => $cycle->disqualification_score,
            'is_override' => $outcome === FilteringOutcome::Qualified && $this->isBelow($initial, $cycle),
            'reason' => $reason,
            'note' => $note,
            'decided_by_user_id' => $actor->id,
        ]);
    }

    public function isBelow(Percent $initial, AwardCycle $cycle): bool
    {
        return $initial->tenThousandths < $cycle->disqualification_score->tenThousandths;
    }
}
