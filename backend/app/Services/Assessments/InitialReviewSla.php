<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use Illuminate\Support\Facades\Date;

final class InitialReviewSla
{
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
}
