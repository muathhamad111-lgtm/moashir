<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CommitteeVoidReason;
use App\Events\Award\CycleProgressChanged;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAssignment;

final readonly class CommitteeRequestCloser
{
    public function __construct(private CommitteeAssignmentVoider $voider, private CommitteeDiscussionCanceller $discussions) {}

    public function closeOnWithdrawal(Assessment $locked): void
    {
        if (! $locked->isAdvanced() || $locked->award_cycle_id === null) {
            return;
        }

        $this->voider->void(CommitteeAssignment::query()->where('assessment_id', $locked->id), CommitteeVoidReason::Withdrawn);
        $this->discussions->cancelOpen($locked);
        CycleProgressChanged::dispatch($locked->award_cycle_id);
    }
}
