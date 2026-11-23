<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Award\AwardCycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

final class InitialReviewSla
{
    private const int MINIMUM_RESUMED_DAYS = 1;

    public function startFor(Assessment $locked): void
    {
        $cycle = $locked->isAdvanced() ? $locked->loadMissing('awardCycle')->awardCycle : null;

        if (! $cycle instanceof AwardCycle) {
            return;
        }

        $locked->forceFill([
            'due_at' => Date::now()->addDays($cycle->initial_review_sla_days),
            'review_sla_days' => $cycle->initial_review_sla_days,
            'overdue_notified_at' => null,
        ]);
    }

    public function remainingDaysOf(Assessment $locked): ?int
    {
        if (! $locked->due_at instanceof Carbon) {
            return null;
        }

        return (int) max(0, ceil(Date::now()->diffInDays($locked->due_at)));
    }

    public function resumeAfter(Assessment $locked, AssessmentCompletionRequest $request): void
    {
        if ($request->review_days_remaining === null) {
            return;
        }

        $locked->forceFill([
            'due_at' => Date::now()->addDays(max(self::MINIMUM_RESUMED_DAYS, $request->review_days_remaining)),
            'overdue_notified_at' => null,
        ]);
    }
}
